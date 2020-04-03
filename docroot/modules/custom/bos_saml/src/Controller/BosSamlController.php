<?php

namespace Drupal\bos_saml\Controller;

use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\simplesamlphp_auth\Controller\SimplesamlphpAuthController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller routines for simplesamlphp_auth routes.
 */
class BosSamlController extends SimplesamlphpAuthController {

  /**
   * Logs the user in via SimpleSAML federation.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirection to either a designated page or the user login page.
   */
  public function authenticate() {
    // Here we might use the LDAP to lookup the user but only if they are
    // "new" in the system.
    $attributes = $this->simplesaml->getAttributes();
    if (!empty($attributes)) {
      $account = \Drupal::entityTypeManager()
        ->getStorage("user")
        ->loadByProperties(["name" => $attributes["username"]]);

      // OK we are going to make this account.
      if (empty($account)) {
        // Get info from LDAP.
        if (empty($attributes["EMailName"])) {
          $ldap = $this->ldapLookup($attributes["username"][0]);
          if (!empty($ldap->data->person)) {
            $person = $ldap->data->person[0];
            $attributes["displayName"][0] = $person->displayname;
            $attributes["EMailName"][0] = $person->mail;
            $attributes["roles"] = (array) $person->ismemberof;
          }
        }

        $account = User::create([
          "name" => $attributes["username"][0],
          "mail" => $attributes["EMailName"][0],
        ])
          ->save();

        // Make a nice display name.
        if (function_exists("bos_core_realname_update")) {
          bos_core_realname_update($attributes["displayName"][0], $account);
        }

      }
    }
    // Do the SAML login now.
    $return = parent::authenticate();

    // Checkout for an original path request.
    $request = \Drupal::request();
    if (NULL !== ($query = $request->query) && $query->has('destination')) {
      $url = Url::fromUserInput($query->get("destination"));
      return new RedirectResponse($url->toString(), RedirectResponse::HTTP_FOUND);
    }

    return $return;
  }

  /**
   * Gather information stored in th city AD using LDAP.
   *
   * @param string $username
   *   The username to lookup in LDAP.
   *
   * @return array
   *   An array of attributes from the LDAP query.
   *
   * @throws \Exception
   */
  private function ldapLookup(string $username) {
    try {
      if (!($ch = curl_init())) {
        throw new \Exception("Bad Request - Cannot create CURL object");
      }
      $token = "8e4fcc89-0546-404a-bcf9-52695683d960";
      $query = "{person(cn: \"" . $username . "\") {cn mail displayname uid ismemberof}}";
      $graphql = json_encode([
        "query" => "query " . $query ,
        "variables" => "{}",
      ]);

      curl_setopt_array($ch, [
        CURLOPT_URL => "https://group-mgmt.boston.gov/graphql",
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => TRUE,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $graphql,
        CURLOPT_HTTPHEADER => [
          "token: " . $token,
          "Content: application/json",
          "Content-Type: application/json",
        ],
      ]);

      $response = curl_exec($ch);

      curl_close($ch);

      return json_decode($response);

    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }

  }

}
