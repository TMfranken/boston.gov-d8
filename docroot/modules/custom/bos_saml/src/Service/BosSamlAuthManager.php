<?php

namespace Drupal\bos_saml\Service;

use Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager;

class BosSamlAuthManager extends SimplesamlphpAuthManager {
  public function getAttribute($attribute) {
    if ($attribute == $this->config->get('mail_attr')) {

    }
    else {
      return parent::getAttribute($attribute);
    }
  }
  public function setAttribute() {
    return;
  }

}
