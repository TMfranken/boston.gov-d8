<?php

namespace Drupal\bos_saml\EventSubscriber;

use Drupal;
use Drupal\bos_core\Controllers\Login\LoginController;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Manages events.
 */
class BosSamlSubscriber implements EventSubscriberInterface {

  /**
   * User account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $user;

  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * BosSamlSubscriber constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user account.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The simplesaml config settings.
   */
  public function __construct(AccountProxyInterface $account, ConfigFactoryInterface $config_factory) {
    $this->user = $account;
    $this->config = $config_factory->get('simplesamlphp_auth.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['checkForRedirection'];
    return $events;
  }

  /**
   *
   */
  public function checkForRedirection(GetResponseEvent $event) {

    // @var $request Symfony\Component\HttpFoundation\Request.
    $request = Drupal::request();
    Drupal::state()->delete('sso_login_url');

    // Check if the ?local param is present.
    if ($request->getPathInfo() == "/user/login") {

      // @var $query Symfony\Component\HttpFoundation\ParameterBag.
      $query = $request->query;
      // @var $response Drupal\Core\Render\HtmlResponse.
      $response = new HtmlResponse();
      // Cache parameter separate from path.
      LoginController::cacheParam('url.query_args:local', $response);

      if ($query->has('local')) {
        // Set SSO to false for local logins.
        if (NULL !== ($whitelist = $this->config->get("sso_login_local_whitelist"))) {
          foreach ($whitelist as $wip) {
            foreach ($request->getClientIps() as $clientip) {
              if ($this->ipMatch($wip, $clientip)) {
                Drupal::state()->set('sso_login_url', FALSE);
                break;
              }
            }
            if (FALSE === Drupal::state()->get('sso_login_url', NULL)) {
              break;
            }
          }
          if (NULL === Drupal::state()->get('sso_login_url', NULL)) {
            $ipaddresses = implode(",", $request->getClientIps());
            Drupal::messenger()
              ->addError("The IPAddress $ipaddresses is not whitelisted. Local login is not possible.");
          }
        }
      }
      else {
        if ($this->user->id() == 0) {
          // User is anonymous and this is not a drush session.
          $allowed = FALSE;

          // Define a list of partial matches for allowed anon urls.
          $redirect_list = [
            "user/login",
          ];

          // Check the whitelisting for urls that anonymous CAN access.
          foreach ($redirect_list as $redirect_from) {
            if (stripos($_SERVER["REQUEST_URI"], $redirect_from) !== FALSE) {
              $allowed = TRUE;
              break;
            }
          }

          if ($allowed) {
            // Redirect to sso.boston.dev.
            $url = Url::fromRoute('simplesamlphp_auth.saml_login', [], ['query' => \Drupal::destination()->getAsArray()]);
            $response = new RedirectResponse($url->toString(), RedirectResponse::HTTP_FOUND);
            $event->setResponse($response);
            $event->stopPropagation();
          }
        }

      }
    }

  }

  /**
   * See if IP matches a given pattern.
   *
   * @param string $ip
   *   IP to test.
   * @param string $ip_pattern
   *   Pattern to test against.
   *
   * @return bool
   *   True if ip matches pattern, false otherwise.
   */
  private function ipMatch(string $ip, string $ip_pattern) {
    if ($ip == $ip_pattern) {
      return TRUE;
    }
    return FALSE;
  }
}
