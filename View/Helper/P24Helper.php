<?PHP
App::uses('FormHelper', 'View/Helper');

class P24Helper extends FormHelper {
  private $options = null;

  private function getSubmitUrl() {
    return 'https://secure.przelewy24.pl/index.php';
  }

  private function parseData($data = null) {
    $result = array();

    foreach ($data as $field => $value) {

    }

    return $result;
  }

  public function create($data = null, $options = array()) {
    if ($data !== null) {
      $this->parseData($data);
    }
    if (!isset($options['url'])) {
      $options['url'] = $this->getSubmitUrl();
    }
    return parent::create(null, $options = array());
  }

  public function hidden($fieldName, $options = array()) {
    if (!isset($options['name'])) {
      $options['name'] = $fieldName;
    }

    if (!isset($options['value']) &&
        isset($this->data[str_replace('p24_', '', $fieldName)])
    ) {
      $options['value'] = $this->data[str_replace('p24_', '', $fieldName)];
    }

    return parent::hidden($fieldName, $options);
  }

  public function data($newData = null) {
    if ($newData === null) {
      return $data;
    } else {
      $data = $this->parseData($newData);
    }
  }

  public function session($data = null) {
    $result = array();

    $result[] = $this->hidden('p24_session_id');
    $result[] = $this->hidden('p24_id_sprzedawcy');

    return join("\n", $result);
  }

  public function price() {
    return $this->hidden('p24_kwota');
  }

  public function callbackUrls() {
    $result = array();

    $result[] = $this->hidden('p24_return_url_ok');
    $result[] = $this->hidden('p24_return_url_error');

    return join("\n", $result);
  }

  public function paymentMethod($method = null) {
    $result = array();

    if (is_null($method)) {
        $result[] = $this->Html->useTag('javascriptlink', 'https://secure.przelewy24.pl/external/formy.php?id=9722&amp;sort=2', '');
        $result[] = $this->Html->useTag('javascriptblock', 'm_formy();', '');
    } else {
        $result[] = $this->hidden('p24_metoda');
    }

    return join("\n", $result);
  }

  public function description($data = null) {
    $result[] = $this->hidden('p24_opis');
    if(Configure::read() > 0) {
       //'TEST_OK';
    }
  }

  public function crc() {
    return $this->hidden('p24_crc');
  }

  public function optionalFields($data = null) {
    $result = array();

    $result[] = $this->hidden('p24_klient');
    $result[] = $this->hidden('p24_adres');
    $result[] = $this->hidden('p24_kod');
    $result[] = $this->hidden('p24_miasto');
    $result[] = $this->hidden('p24_kraj');
    $result[] = $this->hidden('p24_email');

    $result[] = $this->hidden('p24_language');

    return join("\n", $result);
  }

  public function requiredFields($data = null) {
    $result = array();
    $result[] = $this->session();
    $result[] = $this->price();
    $result[] = $this->callbackUrls();
    $result[] = $this->paymentMethod();
    $result[] = $this->description();
    $result[] = $this->crc();
    return join("\n", $result);
  }

  public function allFields($data = null) {
    $result = array();
    $result[] = $this->requiredFields();
    $result[] = $this->optionalFields();
    return join("\n", $result);
  }
}