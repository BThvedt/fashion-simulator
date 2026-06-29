<?php

declare(strict_types=1);

namespace Drupal\simple_oauth_password_grant\Repository;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simple_oauth\Entities\UserEntity;
use Drupal\user\UserAuthInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The user repository.
 */
class UserRepository implements UserRepositoryInterface {

  /**
   * UserRepository constructor.
   */
  public function __construct(
    protected UserAuthInterface $userAuth,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected FloodInterface $flood,
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   *   On flood block.
   */
  public function getUserEntityByUserCredentials($username, $password, $grantType, ClientEntityInterface $clientEntity): ?UserEntityInterface {
    $floodConfig = $this->configFactory->get('user.flood');
    $request = $this->requestStack->getCurrentRequest();
    $floodControlTriggered = NULL;

    // This is taken from the basic_auth module.
    // @see \Drupal\basic_auth\Authentication\Provider\BasicAuth::authenticate()
    // Do not allow any login from the current user's IP if the limit has been
    // reached. Default is 50 failed attempts allowed in one hour. This is
    // independent of the per-user limit to catch attempts from one IP to log
    // in to many different user accounts.  We have a reasonably high limit
    // since there may be only one apparent IP for all users at an institution.
    if ($this->flood->isAllowed('oauth2_password_grant.failed_login_ip', $floodConfig->get('ip_limit'), $floodConfig->get('ip_window'))) {
      $account = $this->getAccount($username);
      if ($account) {
        if ($floodConfig->get('uid_only')) {
          // Register flood events based on the uid only, so they apply for any
          // IP address. This is the most secure option.
          $identifier = $account->id();
        }
        else {
          // The default identifier is a combination of uid and IP address. This
          // is less secure but more resistant to denial-of-service attacks that
          // could lock out all users with public user names.
          $identifier = $account->id() . '-' . $request->getClientIP();
        }

        // Don't allow login if the limit for this user has been reached.
        // Default is to allow 5 failed attempts every 6 hours.
        if ($this->flood->isAllowed('oauth2_password_grant.failed_login_user', $floodConfig->get('user_limit'), $floodConfig->get('user_window'), $identifier)) {
          // @todo Use authenticateWithFloodProtection when #2825084 lands.
          $uid = $this->userAuth->authenticate($account->getAccountName(), $password);

          if ($uid) {
            $this->flood->clear('oauth2_password_grant.failed_login_user', $identifier);

            $user = new UserEntity();
            $user->setIdentifier((string) $uid);

            return $user;
          }
          else {
            // Register a per-user failed login event.
            $this->flood->register('oauth2_password_grant.failed_login_user', $floodConfig->get('user_window'), $identifier);
          }
        }
        else {
          $floodControlTriggered = 'user';
        }
      }
    }
    else {
      $floodControlTriggered = 'ip';
    }

    // Always register an IP-based failed login event.
    $this->flood->register('oauth2_password_grant.failed_login_ip', $floodConfig->get('ip_window'));

    // Create proper flood block responses by throwing an OAuthServerException.
    if (!!$floodControlTriggered) {
      if ($floodControlTriggered === "user") {
        $message = new TranslatableMarkup('There has been more than one failed login attempt for this account. It is temporarily blocked. Try again later.');

        throw new OAuthServerException((string) $message, 11, 'flood_user_blocked', 403);
      }
      else {
        $message = new TranslatableMarkup('Too many failed login attempts from your IP address. This IP address is temporarily blocked. Try again later.');

        throw new OAuthServerException((string) $message, 11, 'flood_ip_blocked', 403);
      }
    }

    return NULL;
  }

  /**
   * Get an active account by username or email.
   *
   * @param string $usernameOrEmail
   *   The username or email address.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The account or NULL if not found.
   */
  protected function getAccount(string $usernameOrEmail): ?AccountInterface {
    // If username contains @, search for user by email first.
    if (strpos($usernameOrEmail, '@') !== FALSE) {
      $account = $this->getAccountByProperty('mail', $usernameOrEmail);
      if ($account) {
        return $account;
      }
    }

    return $this->getAccountByProperty('name', $usernameOrEmail);
  }

  /**
   * Get an active account by property.
   *
   * @param string $property
   *   The property to search for.
   * @param string $value
   *   The value to search for.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The account or NULL if not found.
   */
  protected function getAccountByProperty(string $property, string $value): ?AccountInterface {
    $accountSearch = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties([$property => $value, 'status' => 1]);

    if ($account = reset($accountSearch)) {
      /** @var \Drupal\Core\Session\AccountInterface $account */

      return $account;
    }

    return NULL;
  }

}
