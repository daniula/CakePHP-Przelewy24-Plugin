<?php
App::uses('Component', 'Controller');
App::uses('Router', 'Routing');
App::uses('HttpSocket', 'Network/Http');

class P24Component extends Component {
  public $component = array('Session');

  private $controller;

  private $__plugiName = null;
  private function getPluginName() {
    if ($this->__plugiName === null) {
      $file = __FILE__;

      while (basename($file) !== 'Plugin') {
        $this->__plugiName = basename($file);
        $file = dirname($file);
      }

    }

    return $this->__plugiName;
  }

  private function getUrl() {
    return sprintf('https://%s.przelewy24.pl/', (Configure::read('debug') == 0 ? 'secure' : 'sandbox'));
  }

  private function getHelperSettings() {
    if (array_key_exists($this->getPluginName().'.P24', $this->controller->helpers)) {
      return $this->controller->helpers[$this->getPluginName().'.P24']
        ? $this->controller->helpers[$this->getPluginName().'.P24']
        : array();
    } else if (in_array($this->getPluginName().'.P24', $this->controller->helpers)) {
      return array();
    } else {
      return false;
    }
  }

  private function setHelperSettings($settings = null) {
    if (is_null($settings)) {
      $settings = $this->settings;
    }
    $settings = array_merge($settings, $this->getHelperSettings());

    foreach (array('ok', 'error') as $p) {
      if (!isset($settings['return_url_'.$p])) {
        $this->settings['return_url_'.$p] = Router::url($this->controller->here.'?p24='.$p, true);
        $settings['return_url_'.$p] = Router::url($this->controller->here.'?p24='.$p, true);
      }
    }

    $this->controller->helpers[$this->getPluginName().'.P24'] = $settings;
  }

  private function requestPaymentMethods() {
    if (!$payments = Cache::read('p24.payments')) {

      App::uses('Xml', 'Utility');
      $http = new HttpSocket();
      $http->get($this->settings['url'].'external/formy.php', array('id' => $this->settings['id_sprzedawcy']));
      $response = explode("\n", $http->response->body);
      $payments = array();
      foreach ($response as &$line) {
        if (preg_match('/^OPIS\[([0-9]+)\]=(.+)/', $line, $opis)) {
          $payments[$opis[1]]['info'] = pl_iconv(preg_replace("/[';]/", '', strip_tags($opis[2])));
        }

        if (strpos($line, 'm_form') !== false) {
          $line = str_replace('function m_formy() {document.write("', '', $line);
          $line = str_replace('");}', '', $line);
          $line = str_replace('\"', '"', $line);
          preg_match_all('`(disabled)? /><label for="pf([0-9]+)".*?>(.*?)</label>`', $line, $labels);
          foreach ($labels[2] as $i => $number) {
            $payments[$number]['name'] = pl_iconv($labels[3][$i]);
            $payments[$number]['disabled'] = !empty($labels[1][$i]);
          }
        }
      }

      Cache::write('p24.payments', $payments, '+1 hours');
    }

    return $payments;
  }

  public function updateSessionId($pushToHelper = true) {
    $this->settings['session_id'] = $this->controller->Session->id();
    if ($pushToHelper) {
      $this->setHelperSettings();
    }
  }

  public function initialize(&$controller) {
    parent::initialize($controller);
    $this->controller = $controller;
  }

  public function init() {
    if (!isset($this->settings['session_id'])) {
      $this->updateSessionId(false);
    }

    if (!isset($this->settings['url'])) {
      $this->settings['url'] = $this->getUrl();
    }

    if (!isset($this->settings['payments'])) {
      $this->settings['payments'] = $this->requestPaymentMethods();
    }

    if (isset($this->settings['kwota']) && !isset($this->settings['crc'])) {
      $this->crc();
    }

    $this->setHelperSettings($this->settings);
  }

  public function log($message, $attachSettings = true, $type = LOG_ERROR) {
    if ($attachSettings) {
      $settings = $this->settings;
      unset($settings['payments']);
      parent::log(array($message) + $settings, $type);
    } else {
      parent::log($message, $type);
    }
  }

  public function verify($data = null) {
    $request = $this->controller->request;
    if (is_null($data)) {
      $data = $request->data;
    }


    if ($this->remoteCRC($data) !== $data['p24_crc']) {
      $this->log('CRC value is incorrect');
      return false;
    }

    foreach (array('session_id', 'id_sprzedawcy') as $p) {
      if ($this->settings[$p] !== $data['p24_'.$p]) {
        $this->log('Values of '.$p.' don\'t match');
        return false;
      }
    }

    $http = new HttpSocket();
    $result = $http->post($this->settings['url'].'transakcja.php', $data);
    $result = explode("\n", $result->body);


    switch (strtoupper($result[1])) {
      case 'TRUE':
        if (isset($this->settings['store'])) {
          list($model, $method) = $this->settings['store'];
          return call_user_method_array($method, $this->controller->{$model}, array($data, $request));
        } else {
          return true;
        }
      break;
      case 'ERR':
        $this->log('Error: '.$result[2]);
        return false;
      break;
      default:
        $this->log(array('Unknown error') + $result);
        return false;
      break;
    }
  }

  public function crc($params = null) {
    if (is_numeric($params)) {
      $params = array('amount' => $params);
    }

    if (is_null($params)) {
      if (isset($this->settings['kwota'])) {
        $params = array('amount' => $this->settings['kwota']);
      } else {
        $params = array();
      }
    }

    $params = array_merge($this->settings, $params);
    if (!isset($this->settings['crc'])) {
      $this->settings['crc'] = $this->_crc($params);
    }
    $this->setHelperSettings();
    return $this->settings['crc'];
  }

  public function remoteCRC($data) {
    return $this->_crc(
      $data['p24_session_id'],
      $data['p24_order_id'],
      $data['p24_kwota'],
      $this->settings['hash']
    );
  }

  private function _crc($session_id = null, $seller_id = null, $amount = null, $hash = null) {
    if (is_array($session_id) && is_null($seller_id) && is_null($amount) && is_null($hash)) {
      $data = $session_id;
      $session_id = $data['session_id'];
      $seller_id = $data['id_sprzedawcy'];
      $amount = $data['amount'];
      $hash = $data['hash'];
    }

    if (in_array(null, array($session_id, $seller_id, $amount, $hash))) {
      throw new Exception('One of parameters is missing');
    }

    return md5(join('|', array($session_id, $seller_id, $amount, $hash)));
  }

}

