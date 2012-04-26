<?php

class sfOauthApplicationActions extends sfActions
{


	protected function getClientId(sfWebRequest $request)
	{
		$client_id = $request->getParameter('client_id'); // OAuth 2.0
		if ($client_id == NULL)
		  $client_id = $request->getParameter('oauth_consumer_key', ' ');  // OAuth 1.0

		return $client_id;
	}
  /**
   *  Authorize an Application
   * */
  public function executeAuthorize(sfWebRequest $request)
  {
    $user_id = $this->getUser()->getAttribute('user_id', null, 'sfGuardSecurityUser');
    $client_id = $this->getClientId($request);

    $this->consumer = sfOauthServerConsumerQuery::create()->findOneByConsumerKey($client_id); // Check if the client_id exist
    $this->forward404Unless($this->consumer);

    if ($this->consumer->getProtocole() == 1) // OAuth 1.0
    {
      $this->callback = $request->getParameter('oauth_callback', $this->consumer->getCallback());
      $oauthServer = new sfoauthserver(new sfOAuthDataStore());
      $this->token = $request->getParameter('oauth_token');
      $this->forward404Unless($oauthServer->checkAuthorizeRequest($request));
      if (!SfOauthServerUserScopeQuery::create()->isApplicationAuthorized($this->consumer->getId(), $user_id, $this->consumer->getScope()))
      {
        if ($request->isMethod(sfRequest::POST))
        {
          if ($request->getParameter('accept') == 'Yes')
            $oauthServer->authorizeToken($this->token, $user_id);
          else
            $param = '?error_reason=user_denied&error=access_denied&error_description=The+user+denied+your+request';
          return $this->redirect($this->callback . $param);
        }
      }
      else
      {
        $oauthServer->authorizeToken($this->token, $user_id);
        return $this->redirect($this->callback . $param);
      }
    } else if ($this->consumer->getProtocole() == 2) // Oauth 2.0
    {
      $this->redirect_uri = $request->getParameter('redirect_uri', $this->consumer->getCallback());
      if ($this->redirect_uri == NULL)
        $this->redirect_uri = $this->consumer->getCallback();

      $oauth = new sfOauth2Server();
      $oauth->setUserId($user_id);
      if ($request->isMethod(sfRequest::POST))
      {
        if ($request->getParameter('accept') == 'Yes')
        {
          SfOauthServerUserScopeQuery::create()->authorizeApplication($this->consumer->getId(), $user_id, $this->consumer->getScope());
          $oauth->finishClientAuthorization($request->getParameter('accept') == 'Yes', array_merge($_POST, array('scope' => $this->consumer->getScope())));
        }
      } else if (SfOauthServerUserScopeQuery::create()->isApplicationAuthorized($this->consumer->getId(), $user_id, $this->consumer->getScope()))
        $oauth->finishClientAuthorization(1, array_merge($_GET, array('scope' => $this->consumer->getScope())));
    }
  }

  public function executeDeauthorize(sfWebRequest $request)
  {
	  $oauth_token = $request->getParameter('oauth_token');

	  if ($oauth_token)
	  {
		  $nbUpdatedRows = SfoauthserveraccesstokenQuery::create()
				  ->filterByToken($oauth_token)
				  ->update(array('Expires' => time()));
	  }

      return sfView::NONE;
  }

  public function executeList(sfWebRequest $request)
  {
    $this->applications = sfOauthServerUserScopeQuery::create()->getApplicationsOf($this->getUser()->getAttribute('user_id', null, 'sfGuardSecurityUser'));
  }

  public function executeDelete(sfWebRequest $request)
  {
    $this->forward404Unless($application = sfOauthServerUserScopeQuery::create()->find(array($request->getParameter('id'))), sprintf('Object does not exist (%s).', $request->getParameter('id')));

    if ($application->getUserId() == $this->getUser()->getAttribute('user_id', null, 'sfGuardSecurityUser'))
      $application->delete();

    $this->redirect('application/list');
  }

}
