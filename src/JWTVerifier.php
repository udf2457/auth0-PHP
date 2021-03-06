<?php

namespace Auth0\SDK;

use Auth0\SDK\Exception\CoreException;
use Auth0\SDK\Exception\InvalidTokenException;
use Auth0\SDK\Exception\ApiException;
use Auth0\SDK\Helpers\JWKFetcher;
use Firebase\JWT\JWT;

class JWTVerifier {

    protected $JWKFetcher = null;
    protected $suported_algs = null;
    protected $valid_audiences = null;
    protected $authorized_iss = null;
    protected $client_secret = null;

    /**
     * JWTVerifier Constructor.
     *
     * Configuration:
     *     - cache                  (CacheHandler)  Optional. Should be an instance of CacheHandler that is going to be used to cache the JWKs
     *     - suported_algs          (Array)  Optional. The list of supported algorithms. By default only HS256
     *     - client_secret          (String)  Required (if supported HS256). The Auth0 application secret.
     *     - valid_audiences        (Array)  Required. The list of audiences accepted by the service.
     *     - authorized_iss         (Array) Required (if supported RS256). The list of issuers trusted by the service.
     *     - guzzle_options         (Array) Optional Extra configuration options sent to guzzle.
     *
     */
    public function __construct($config) {

        $cache = null;
        $guzzleOptions = [];

        if (isset($config['cache'])) {
            $cache = $config['cache'];
        }
        if (isset($config['guzzle_options'])) {
            $guzzleOptions = $config['guzzle_options'];
        }

        if (!isset($config['suported_algs'])) {
            $config['suported_algs'] = ['HS256'];
        }

        if (!isset($config['valid_audiences'])) {
            throw new CoreException('The audience is mandatory');
        }

        if (!isset($config['authorized_iss'])) {
            if (in_array('RS256', $config['suported_algs'])) {
                throw new CoreException('The iss is mandatory when accepting RS256 signed tokens');
            } else {
                $config['authorized_iss'] = [];
            }
        }

        if (in_array('HS256', $config['suported_algs']) && !isset($config['client_secret'])) {
            throw new CoreException('The client_secret is mandatory when accepting HS256 signed tokens');
        }

        $this->suported_algs = $config['suported_algs'];
        $this->valid_audiences = $config['valid_audiences'];
        $this->authorized_iss = $config['authorized_iss'];
        if (in_array('HS256', $config['suported_algs']) {
            $this->client_secret = $config['client_secret'];
        }

        $this->JWKFetcher = new JWKFetcher($cache, $guzzleOptions);
    }

    public function verifyAndDecode($jwt) {
        
        $tks = explode('.', $jwt);
        if (count($tks) != 3) {
            throw new InvalidTokenException('Wrong number of segments');
        }

        $headb64 = $tks[0];
        $body64 = $tks[1];
        $head = json_decode(JWT::urlsafeB64Decode($headb64));

        if ( !is_object($head) || ! isset($head->alg))
        {
              throw new InvalidTokenException("Invalid token");
        }

        if (!in_array($head->alg, $this->suported_algs)) {
            throw new InvalidTokenException("Invalid signature algorithm");
        }

        if ($head->alg === 'RS256') {
            $body = json_decode(JWT::urlsafeB64Decode($body64));
            if ( !in_array($body->iss, $this->authorized_iss) ) {
                throw new CoreException("We can't trust on a token issued by: `{$body->iss}`.");
            }
            $secret = $this->JWKFetcher->fetchKeys($body->iss);
        } elseif ($head->alg === 'HS256') {
            $secret = JWT::urlsafeB64Decode($this->client_secret);
        } else {
            throw new InvalidTokenException("Invalid signature algorithm");
        } 
        
        try {
            // Decode the user
            $decodedToken = JWT::decode($jwt, $secret, array('HS256', 'RS256'));
            // validate that this JWT was made for us
            $audience = $decodedToken->aud;
            if (! is_array($audience)) {
                $audience = [$audience];
            }
            if (count(array_intersect($audience, $this->valid_audiences)) == 0) {
                throw new InvalidTokenException("This token is not intended for us.");
            }
        } catch(\Exception $e) {
            throw new CoreException($e->getMessage());
        }
        return $decodedToken;
    }
}
