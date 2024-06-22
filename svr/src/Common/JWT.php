<?php
namespace LWMIS\Common;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;

class JWT
{
  private Configuration $config;
  private Signer $signer;
  private Key $private_key;
  private Key $public_key;

  function __construct()
  {
    $this->signer = new Signer\Rsa\Sha256();
    $this->private_key = InMemory::file('file://' . __DIR__ . '/jwt-private.key');
    $this->public_key = InMemory::file('file://' . __DIR__ . '/jwt-public.key');

    $this->config = Configuration::forAsymmetricSigner(
      $this->signer,
      $this->private_key,
      $this->public_key
    );
    assert($this->config instanceof Configuration);
  }

  function generateToken(object $payload): string
  {
    $now = new DateTimeImmutable();

    $builder = $this->config->builder();
    assert($builder instanceof Builder);
    // Configures the issuer (iss claim)
    $builder->issuedBy($this->getDomainName());
    // Configures the audience (aud claim)
    $builder->permittedFor($this->getDomainName());
    // Configures the id (jti claim)
    $builder->identifiedBy('4f1g23a12aa');
    // Configures the time that the token was issue (iat claim)
    $builder->issuedAt($now);
    // Configures the time that the token can be used (nbf claim)
    $builder->canOnlyBeUsedAfter($now->modify('+0 second'));
    // Configures the expiration time of the token (exp claim)
    $builder->expiresAt($now->modify('+15 minutes'));
    // Configures a new claim, called "uid"
    // ->withClaim('uid', 1)
    // Configures a new header, called "foo"
    // ->withHeader('foo', 'bar')

    // \var_dump($builder);

    if (isset($payload) && gettype($payload) === "object") {
      // configures a new claim, called "payload"
      $builder->withClaim('payload', $payload);
    } else {
      throw new Exception("Payload not found or Payload should be an object.");
    }

    // Builds a new token
    $token = $builder->getToken($this->config->signer(), $this->config->signingKey());
    assert($token instanceof Plain);

    return $token->toString();
  }

  private function getDomainName(): string
  {
    $domain_name = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['SERVER_NAME'];
    return $domain_name;
  }

  /**
   * @throws Exception
   */
  function validateToken($direct_bearer = null)
  {
    if (is_null($direct_bearer)) {
      $bearer = $this->extractToken();
    } else {
      $bearer = $direct_bearer;
    }

    $now = new DateTimeImmutable("now", new DateTimeZone('Asia/Kolkata'));
    $token = $this->config->parser()->parse($bearer ?? '');
//    assert($token instanceof UnencryptedToken);

    $this->config->setValidationConstraints(
      new IdentifiedBy('4f1g23a12aa'),
      new IssuedBy($this->getDomainName()),
      new PermittedFor($this->getDomainName()),
//       new RelatedTo(),
      new SignedWith($this->signer, $this->public_key),
      new StrictValidAt(new FrozenClock($now))
    );

    $constraints = $this->config->validationConstraints();

    if ($this->config->validator()->validate($token, ...$constraints)) {
      $claims = $token->claims();
      if ($claims->has('payload')) {
        return $claims->get('payload');
      } else {
        throw new Exception("Invalid authentication data. Please Login and try again.");
      }
    } else {
      throw new Exception("Token is not valid / Expired");
    }
  }

  /**
   * @throws Exception
   */
  private function extractToken()
  {
    $authorizationData = null;
    foreach (getallheaders() as $name => $value) {
      if (strtolower($name) == strtolower("Authorization"))
        $authorizationData = $value;
    }
    if (gettype($authorizationData) == "string") {
      list($jwt) = sscanf($authorizationData, 'Bearer %s');
      return $jwt;
    } else
      throw new Exception("Authorization Data not found.");
  }

//  function print() {
//    \var_dump($this->config->signer());
//    echo "<hr>";
//    \var_dump($this->config->signingKey());
//    echo "<hr>";
//    \var_dump($this->config->verificationKey());
//    echo "<hr>";
//    \var_dump($this->config->validator());
//    echo "<hr>";
//    \var_dump($this->config->validationConstraints());
//    echo "<hr>";
//    \var_dump($this->config->parser());
//    echo "<hr>";
//  }
}

?>
