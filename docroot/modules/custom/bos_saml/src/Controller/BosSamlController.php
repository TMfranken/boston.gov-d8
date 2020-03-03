<?php

namespace Drupal\bos_saml\Controller;

use Drupal\simplesamlphp_auth\Controller\SimplesamlphpAuthController;

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
    $a = $this->simplesamlAuth->getDefaultEmail();
    $return = parent::authenticate();
    return $return;
  }

}
