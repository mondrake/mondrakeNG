<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;
use mondrakeNG\mm\core\MMUtils;

class MMUserLogin extends MMObj {

	public function listUserLogins($userId, $beforeThan = NULL) {
		$sqlq = "user_id = $userId";
		if ($beforeThan)
			$sqlq .= " and mm_trust_token_expiration <= '$beforeThan'";
		return $this->readMulti($sqlq);
	}

	public function readToken($id) {
		return $this->readSingle("mm_trust_token = '$id'" );
//		return $this->readSingle("mm_trust_token like '%'" );
	}

	// user authentication routine
	// authParms:
	//   I/O	mmToken - token
	//   I		mmLoginUser - login user
	//   I		mmLoginPass - login password
	//   O		authResult - authorization process feedback
	//   O		authMsg[] - authorization process feedback
	//   I/O	mmTokenSecsToExpiration - seconds to token expiration
	//	 O		mmTokenExpirationTs - time when token expires
	//   I/O	mmEnvironment - environment id
	//   I   	mmClient - client id
	public function userAuthenticate(&$authParms) {
		$user = new MMUser;
		$gmnow = gmdate('Y-m-d H:i:s');
		$expirationDateTime = date('Y-m-d H:i:s', strtotime($gmnow) + $authParms['mmTokenSecsToExpiration']);
		$isValidated = FALSE;

		// validates via token if valid
		if(isset($authParms['mmToken']))	{
			$res = $this->readToken($authParms['mmToken']);

			// removes token info for security reasons
			unset($authParms['mmToken']);  // = NULL;

			if ($res) {
				if($this->mm_trust_token_expiration < $gmnow) {
					$user->setSessionContext(array( 	'user' => 3, ));
					$this->delete();
					$authParms['authResult'] = 10;
					$authParms['authMsg'][] = 'Validation token expired.';
				}
				else {
					$this->last_login_ts = $gmnow;
					$this->mm_trust_token_expiration = $expirationDateTime;
					$this->http_user_agent = $_SERVER['HTTP_USER_AGENT'];
					$this->remote_addr = $_SERVER['REMOTE_ADDR'];
					$this->dbAction = 'update';
					$user->read($this->user_id);
					$isValidated = TRUE;
				}
			}
			else	{
				$authParms['authResult'] = 11;
				$authParms['authMsg'][] = 'Validation token invalid.';
			}
		}

		// validates via login user/pass
		if(!$isValidated and isset($authParms['mmLoginUser']))	{
			$user->getUserLogin($authParms['mmLoginUser']);
			if ($user->login_id == null || $user->login_pass <> $authParms['mmLoginPass'])	{
				$authParms['authResult'] = 2;
				$authParms['authMsg'][] = "Invalid credentials (02).";
			}
			else	{
				$this->user_id = $user->user_id;
				$this->remote_addr = $_SERVER['REMOTE_ADDR'];
				$this->http_user_agent = $_SERVER['HTTP_USER_AGENT'];
				$this->mm_trust_token = MMUtils::generateToken(20);
				$this->last_login_ts = $gmnow;
				$this->mm_trust_token_expiration = $expirationDateTime;
				$this->dbAction = 'create';
				$authParms['mmToken'] = $this->mm_trust_token;
				$user->read($this->user_id);
				$isValidated = TRUE;
			}
		}

		// removes passwd info for security reasons
		unset($authParms['mmLoginPass']); // = NULL;

		if ($isValidated)	{
			if($user->is_login_enabled == 0) {			// checks user loggable
				$user->setSessionContext(array( 	'user' => 3, ));
				$this->delete();
				$authParms['authResult'] = 1;
				$authParms['authMsg'][] = 'Invalid credentials (01).';
				return FALSE;
			}
			else	{
				//
				// from here auth is OK and process proceeds with completion
				//

				// checks environment
				if (isset($authParms['mmEnvironment']))	{
					// todo check if auth
				}
				else	{
					$authParms['mmEnvironment'] = $user->currentEnvironmentId;
				}
				$this->environment_id = $authParms['mmEnvironment'];

				$this->client_id = $authParms['mmClient'];

				// set user session context
				$user->setSessionContext(array( 	'user' => $this->user_id,
													'environment' => $this->environment_id,
													'client' => $this->client_id,));

				// creates/updates MMUserLogin record
				if ($this->dbAction == 'create')
					$this->create();
				else
					$this->update();

				// retrieves and deletes expired logins for this user
				$expLogins = self::listUserLogins($this->user_id, $gmnow);
				if($expLogins)
					foreach ($expLogins as $a)
						$a->delete();

				// finalise response output array
				$authParms['authResult'] = 0;
				$authParms['mmTokenExpirationTs'] = $this->mm_trust_token_expiration;

				// geolocate from IP address
				// this no longer works $this->geoLocate();
				return TRUE;
			}
		}
		return FALSE;
	}

	// user passthrough authentication routine
	// authParms:
	//   I/O	mmToken - token
	//   I		mmUserToken - token
	//   I		mmClientToken - token
	//	 I		loginTime - time of external login
	//   O		authResult - authorization process feedback
	//   O		authMsg[] - authorization process feedback
	//   I/O	mmTokenSecsToExpiration - seconds to token expiration
	//	 O		mmTokenExpirationTs - time when token expires
	//   I/O	mmEnvironment - environment id
	//   I   	mmClient - client id
	public function userPassThrough(&$authParms) {
		$mmUser = new MMUser;
		$gmnow = date('Y-m-d H:i:s', $authParms['loginTime']);
		$expirationDateTime = date('Y-m-d H:i:s', strtotime($gmnow) + $authParms['mmTokenSecsToExpiration']);
		$isValidated = FALSE;

		// validates via token
		if(isset($authParms['mmToken']))	{
			$res = $this->readToken($authParms['mmToken']);
			if ($res) {								// token found in db
				$this->last_login_ts = $gmnow;
				$this->mm_trust_token_expiration = $expirationDateTime;
				$this->http_user_agent = $_SERVER['HTTP_USER_AGENT'];
				$this->remote_addr = $_SERVER['REMOTE_ADDR'];
				$this->dbAction = 'update';
				$mmUser->read($this->user_id);
				$isValidated = TRUE;
			}
			else	{								// token non found in db
				$mmUser->getUserFromToken($authParms['mmUserToken']);
				$this->user_id = $mmUser->user_id;
				$this->remote_addr = $_SERVER['REMOTE_ADDR'];
				$this->http_user_agent = $_SERVER['HTTP_USER_AGENT'];
				$this->mm_trust_token = $authParms['mmToken'];
				$this->last_login_ts = $gmnow;
				$this->mm_trust_token_expiration = $expirationDateTime;
				$this->dbAction = 'create';
				$mmUser->read($this->user_id);
				$isValidated = TRUE;
			}
		}

		if ($isValidated)	{
			if($mmUser->is_login_enabled == 0) {			// checks user loggable
				$mmUser->setSessionContext(array( 	'user' => 3, ));
				$this->delete();
				$authParms['authResult'] = 1;
				$authParms['authMsg'][] = 'User disabled.';
				return FALSE;
			}
			else	{
				//
				// from here auth is OK and process proceeds with completion
				//

				// checks environment
				if (isset($authParms['mmEnvironment']))	{
					// todo check if auth
				}
				else	{
					$authParms['mmEnvironment'] = $mmUser->currentEnvironmentId;
				}
				$this->environment_id = $authParms['mmEnvironment'];

				$mmClient = new MMClient;
				$mmClient->getClientFromToken($authParms['mmClientToken']);
				$this->client_id = $mmClient->client_id;
				$authParms['mmClient'] = $this->client_id;

				// set user session context
				$mmUser->setSessionContext(array( 	'user' => $this->user_id,
													'environment' => $this->environment_id,
													'client' => $this->client_id,));

				// creates/updates MMUserLogin record
				if ($this->dbAction == 'create')
					$this->create();
				else
					$this->update();

				// retrieves and deletes expired logins for this user
				$expLogins = self::listUserLogins($this->user_id, $gmnow);
				if($expLogins)
					foreach ($expLogins as $a)
						$a->delete();

				// finalise response output array
				$authParms['authResult'] = 0;
				$authParms['mmTokenExpirationTs'] = $this->mm_trust_token_expiration;

				// geolocate from IP address
				//$this->geoLocate();
				return TRUE;
			}
		}
		return FALSE;
	}

	// user login record removal
	public function removeUserLogin($tok) {
		$res = $this->readToken($tok);
		if ($res) {
			$this->delete();
			return TRUE;
		}
		else
			return FALSE;
	}

	private function geoLocate() {
		$ipGeoLoc = new MMIpLocation;
		$ipGeoLoc->locate($this->remote_addr);
	}
}
