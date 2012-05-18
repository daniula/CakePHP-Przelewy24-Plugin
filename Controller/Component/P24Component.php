<?php
App::uses('Component', 'Controller');

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

  private function setHelperSettings($settings) {
    $settings = array_merge($settings, $this->getHelperSettings());

    foreach (array('ok', 'error') as $p) {
      if (!isset($settings['return_url_'.$p])) {
        $this->settings['return_url_'.$p] = Router::url($this->controller->here.'?p24='.$p, true);
        $settings['return_url_'.$p] = Router::url($this->controller->here.'?p24='.$p, true);
      }
    }

    $this->controller->helpers[$this->getPluginName().'.P24'] = $settings;
  }

  public function initialize(&$controller) {
    parent::initialize($controller);
    $this->controller = $controller;

    if (!isset($this->settings['session_id'])) {
      $this->settings['session_id'] = $this->controller->Session->id();
    }



    $this->setHelperSettings($this->settings);
  }

  function verify() {
      $params = array();
      $result = array();
      $params[] = "p24_id_sprzedawcy=".$this->data['p24_id_sprzedawcy'];
      $params[] = "p24_session_id=".$this->data['p24_session_id'];
      $params[] = "p24_order_id=".$this->data['p24_order_id'];
      $params[] = "p24_kwota=".$this->data['p24_kwota'];

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_POST,1);
      if(count($params)) {
          curl_setopt($ch, CURLOPT_POSTFIELDS, join("&",$params));
          curl_setopt($ch, CURLOPT_URL, "https://secure.przelewy24.pl/transakcja.php");
      }
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      $response = explode("\r\n", curl_exec ($ch));
      curl_close ($ch);

      $msg = array();
      $msg[] = 'POST:';
      foreach($this->data as $key => $value) {
          $msg[] = $key.': '.$value;
      }
      $msg[] = '';
      $msg[] = 'Response:';
      $msg = array_merge($msg, $response);

      if(strtoupper($response[1]) == strtoupper($success)
          && $this->data['p24_session_id'] == session_id()
          && $this->data['p24_id_sprzedawcy'] == 9722
      ) {
          if(strtoupper($response[1]) == 'TRUE') {
              if(Configure::read() > 0) {
                  $this->data['p24_order_id_full'] += rand(0,1000);
              }
              if($this->Transaction->find('count', array('conditions' => array('order_id' => $this->data['p24_order_id_full'])))) {
                  $this->Session->setFlash('Ta transakcja została już zarejestrowana');
                  return $this->redirect(array('action' => 'payin'));
              } else {
                  $this->Transaction->create(array(
                      'user_id' => $this->getUserId(),
                      'order_id' => $this->data['p24_order_id_full'],
                      'amount' => $this->data['p24_kwota'],
                      'description' => 'Wpłata',
                      'finished' => true,
                      'creditcard' => $this->data['p24_karta'],
                      'ip' => env('REMOTE_ADDR'),
                      'error' => null,
                  ));
                  if($this->Transaction->save()) {
                      $cash = intval($this->data['p24_kwota']);
                      $this->User->id = $user_id = $this->getUserId();
                      $this->User->query("UPDATE users SET cash = cash + $cash WHERE id = $user_id");
                      $this->Session->write('loggedin.User.cash', $this->User->field('cash'));
                      return $this->render('payin-success');
                  } else {
                      sendLog($msg, 'Transaction not saved');
                      return $this->render('payin-error');
                  }
              }
          } elseif(strtoupper($response[1]) == 'ERR') {
              sendLog($msg, 'Transaction failed');
              return $this->render('payin-error');
          } else {
              sendLog($msg, 'Veryfing transaction failed');
              return $this->render('payin-error');
          }
      } else {
          sendLog($msg, 'Veryfing transaction failed');
          return $this->render('payin-error');
      }
  }

  public function crc($params = array()) {
    if (is_numeric($params)) {
      $params = array('amount' => $params);
    }
    $params = array_merge($this->settings, $params);
    if (!isset($this->settings['crc'])) {
      $this->settings['crc'] = $this->_crc($params);
    }
    return $this->settings['crc'];
  }

  private function _crc($session_id = null, $seller_id = null, $amount = null, $crckey = null) {
    if (is_array($session_id) && is_null($seller_id) && is_null($amount) && is_null($crckey)) {
      $data = $session_id;
      $session_id = $data['session_id'] ? $data['session_id'] : $data['session'];
      $seller_id = $data['seller_id'] ? $data['seller_id'] : $data['seller'];
      $amount = $data['amount'];
      $crckey = $data['crckey'] ? $data['crckey'] : $data['crc'];
    }

    if (in_array(null, array($session_id, $seller_id, $amount, $crckey))) {
      throw new Exception('One of parameters is missing');
    }

    return md5(join('|', array($session_id, $seller_id, $amount, $crckey)));
  }

}

