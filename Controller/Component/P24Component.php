<?php
define('P24_ID_SPRZEDAWCY', 15820);

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
    $this->controller->helpers[$this->getPluginName().'.P24'] = $settings;
  }

  public function initialize(&$controller) {
    parent::initialize($controller);
    $this->controller = $controller;
    $this->setHelperSettings($this->settings);
  }

  function pay() {
    switch(true) {
        case !empty($this->data['Payin']):
            return $this->render('payin-confirm-data');
        break;
        case !empty($this->data['Confirm']):
            if(!empty($this->data['Confirm']['remember'])) {
                list($new_data['User']['imie'], $new_data['User']['nazwisko']) = explode(' ', $this->data['Confirm']['p24_klient']) + array('','');

                $new_data['Usersdetail']['adres'] = $this->data['Confirm']['p24_adres'];
                $new_data['Usersdetail']['kodpocztowy'] = $this->data['Confirm']['p24_kod'];
                $new_data['Usersdetail']['miasto'] = $this->data['Confirm']['p24_miasto'];
                $new_data['Usersdetail']['inny_kraj'] = $this->data['Confirm']['p24_kraj'];

                if($this->Session->read('loggedin.User.imie') != $new_data['User']['imie']
                    || $this->Session->read('loggedin.User.nazwisko') != $new_data['User']['nazwisko']
                ) {
                    $this->User->id = $this->getUserId();
                    if($this->User->save($new_data['User'])) {
                        $this->Session->write('loggedin.User.imie', $new_data['User']['imie']);
                        $this->Session->write('loggedin.User.nazwisko', $new_data['User']['nazwisko']);
                    }
                }

                if($this->Session->read('loggedin.Usersdetail.adres') != $new_data['Usersdetail']['adres']
                    || $this->Session->read('loggedin.Usersdetail.kodpocztowy') != $new_data['Usersdetail']['kodpocztowy']
                    || $this->Session->read('loggedin.Usersdetail.miasto') != $new_data['Usersdetail']['miasto']
                    || $this->Session->read('loggedin.Usersdetail.inny_kraj') != $new_data['Usersdetail']['inny_kraj']
                ) {
                    $this->User->Usersdetail->id = $this->Session->read('loggedin.Usersdetail.id');
                    if($this->User->Usersdetail->save($new_data['Usersdetail'])) {
                        $this->Session->write('loggedin.Usersdetail.adres', $new_data['Usersdetail']['adres']);
                        $this->Session->write('loggedin.Usersdetail.kodpocztowy', $new_data['Usersdetail']['kodpocztowy']);
                        $this->Session->write('loggedin.Usersdetail.miasto', $new_data['Usersdetail']['miasto']);
                        $this->Session->write('loggedin.Usersdetail.inny_kraj', $new_data['Usersdetail']['inny_kraj']);
                    }
                }
            }

            unset($this->data['Confirm']['remember']);

            $this->data = $this->data['Confirm'];
            $this->data['p24_session_id'] = session_id();
            $this->data['p24_id_sprzedawcy'] = P24_ID_SPRZEDAWCY;
            $this->data['p24_return_url_ok'] = Router::url(array('action' => 'payinEnd', 'true'), true);
            $this->data['p24_return_url_error'] = Router::url(array('action' => 'payinEnd', 'err'), true);
            $this->data['p24_language'] = 'pl';

            return $this->render('payin-payment');
        break;
        default:

        break;
    }
  }

  function verify() {
      $this->data = $_POST;
      $this->data['p24_id_sprzedawcy'] = P24_ID_SPRZEDAWCY;

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

  private function crc($session_id = null, $seller_id = null, $amount = null, $crckey = null) {
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


  }

}

