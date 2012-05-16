<?PHP
App::uses('FormHelper', 'View/Helper');
App::uses('Router', 'Routing');

class P24Helper extends FormHelper {
  private $settings = array();
  private $settingsFields = array(
    'session_id',
    'id_sprzedawcy',
    'klient',
    'adres',
    'kod',
    'miasto',
    'kraj',
    'email',
    'language',
    'kwota',
    'return_url_ok',
    'return_url_error',
    'metoda',
    'opis',
  );

  private function getSubmitUrl() {
    return 'https://secure.przelewy24.pl/index.php';
  }

  public function __construct(View $view, $settings = array()) {
      parent::__construct($view, $settings);
      $this->setSettings($settings);
  }

  public function setSettings($settings = array()) {
    foreach ($settings as $field => $value) {
      if (in_array($field, $this->settingsFields)) {
        if ( in_array($field, array('return_url_error', 'return_url_ok')) && is_array($value) ) {
          $value = Router::url($value, true);
        }
        $this->settings[$field] = $value;
      }
    }

    return $this->settings;
  }

  public function create($settings = null, $options = array()) {
    if ($settings !== null) {
      $this->setSettings($settings);
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
        isset($this->options[$fieldName])
    ) {
      $options['value'] = $this->settings[$fieldName];
    }

    return parent::hidden('p24_'.$fieldName, $options);
  }

  public function session($options = null) {
    $result = array();

    $result[] = $this->hidden('session_id');
    $result[] = $this->hidden('id_sprzedawcy');

    return join("\n", $result);
  }

  public function price() {
    return $this->hidden('kwota');
  }

  public function callbackUrls() {
    $result = array();

    $result[] = $this->hidden('return_url_ok');
    $result[] = $this->hidden('return_url_error');

    return join("\n", $result);
  }

  public function paymentMethod($method = null) {
    $result = array();

    if (is_null($method)) {
        $result[] = $this->Html->useTag('javascriptlink', 'https://secure.przelewy24.pl/external/formy.php?id=9722&amp;sort=2', '');
        $result[] = $this->Html->useTag('javascriptblock', 'm_formy();', '');
    } else {
        $result[] = $this->hidden('metoda');
    }

    return join("\n", $result);
  }

  public function description($settings = null) {
    if(Configure::read() == 0) {
       $result[] = $this->hidden('opis');
    } else {
      $result[] = $this->hidden('opis', array('value' => 'TEST_OK'));
    }
  }

  public function crc() {
    return $this->hidden('crc');
  }

  public function optionalFields($settings = null) {
    $result = array();

    $result[] = $this->hidden('klient');
    $result[] = $this->hidden('adres');
    $result[] = $this->hidden('kod');
    $result[] = $this->hidden('miasto');
    $result[] = $this->hidden('kraj');
    $result[] = $this->hidden('email');

    $result[] = $this->hidden('language');

    return join("\n", $result);
  }

  public function requiredFields($settings = null) {
    $result = array();
    $result[] = $this->session();
    $result[] = $this->price();
    $result[] = $this->callbackUrls();
    $result[] = $this->paymentMethod();
    $result[] = $this->description();
    $result[] = $this->crc();
    return join("\n", $result);
  }

  public function allFields($settings = null) {
    $result = array();
    $result[] = $this->requiredFields();
    $result[] = $this->optionalFields();
    return join("\n", $result);
  }
}