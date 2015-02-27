<?php
class Kwf_GoogleAuth_Auth extends Kwf_User_Auth_Abstract implements Kwf_User_Auth_Interface_Redirect
{
    protected $_clientId;
    protected $_clientSecret;
    protected $_registerRole;

    public function __construct(array $config, $model)
    {
        $this->_clientId = $config['clientId'];
        $this->_clientSecret = $config['clientSecret'];
        $this->_registerRole = isset($config['registerRole']) ? $config['registerRole'] : null;
        parent::__construct($model);
    }

    private function _getClient($redirectUri)
    {
        require_once 'vendor/autoload.php';
        $client = new Google_Client();
        $client->setApplicationName("Koala Framework");
        $client->setClientId($this->_clientId);
        $client->setClientSecret($this->_clientSecret);
        $client->setRedirectUri($redirectUri);
        return $client;
    }

    public function getLoginRedirectFormOptions()
    {
        return array();
    }

    public function getLoginRedirectUrl($redirectBackUrl, $state, $formValues)
    {
        $url = 'https://accounts.google.com/o/oauth2/auth';
        $url .= '?'.http_build_query(array(
            'scope' => 'https://www.googleapis.com/auth/userinfo.email',
            'state'=> $state,
            'redirect_uri' => $redirectBackUrl,
            'response_type' => 'code',
            'client_id' => $this->_clientId,
            'access_type'=>'offline'
        ));
        return $url;
    }

    private function _getUserDataByParams($redirectBackUrl, array $params)
    {
        $client = $this->_getClient($redirectBackUrl);
        $client->authenticate($params['code']);
        $plus = new Google_Service_Plus($client);
        $me = $plus->people->get('me')->toSimpleObject();

        return array(
            'id' => $me->id,
            'email' => $me->emails[0]['value'],
            'firstname' => $me->name['givenName'],
            'lastname' => $me->name['familyName'],
        );
    }

    public function getUserToLoginByParams($redirectBackUrl, array $params)
    {
        $userData = $this->_getUserDataByParams($redirectBackUrl, $params);
        $s = new Kwf_Model_Select();
        $s->whereEquals('google_user_id', $userData['id']);
        $ret = $this->_model->getRow($s);
        if (!$ret && $this->_registerRole) {
            $ret = $this->_model->createUserRow($userData['email']);
            $ret->role = $this->_registerRole;
            $ret->google_user_id = $userData['id'];
            $ret->firstname = $userData['firstname'];
            $ret->lastname = $userData['lastname'];
            if ($ret instanceof Kwf_User_EditRow) $ret->setSendMails(false); //we don't want a register mail
            $ret->save();
        }
        return $ret;
    }

    public function associateUserByParams(Kwf_Model_Row_Interface $user, $redirectBackUrl, array $params)
    {
        $userData = $this->_getUserDataByParams($redirectBackUrl, $params);
        $user->google_user_id = $userData['id'];
        $user->save();
    }

    public function createSampleLoginLinks($absoluteUrl)
    {
        return array();
    }

    public function showInBackend()
    {
        return true;
    }

    public function showInFrontend()
    {
        return true;
    }

    public function getLoginRedirectLabel()
    {
        return array(
            'name' => trlKwfStatic('Google'),
            'linkText' => trlKwfStatic('Google'),
            'icon' => 'kwfGoogleAuth/Kwf/GoogleAuth/signInWithGoogle.png'
        );
    }

    public function allowPasswordForUser(Kwf_Model_Row_Interface $user)
    {
        return true;
    }
}
