<?php

namespace LaravelVault\AuthHandlers;

use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use LaravelVault\Exceptions\KubernetesJWTNotFound;
use LaravelVault\Exceptions\KubernetetsJWTInvalid;
use LaravelVault\VaultClient;

class KubernetesAuth implements AuthContract
{
    protected string $saTokenPath = "/var/run/secrets/kubernetes.io/serviceaccount/token";
    protected string $authEndpoint = 'kubernetes';
    protected string $authRole = 'vault';

    private string $jwtToken = '';

    private Carbon $jwtTokenExpiresAt;

    private Carbon $jwtTokenWarningTime;

    private Carbon $tokenExpiresAt;
    
    private string $token;


    /**
     * @param  VaultClient  $client
     * @param  array        $config
     *
     * @throws KubernetesJWTNotFound
     * @throws KubernetetsJWTInvalid
     */
    public function __construct(
        protected VaultClient $client,
        protected array $config
    ) {
        isset($config['sa_token_path']) && ($this->saTokenPath = $config['sa_token_path']);
        isset($config['auth_endpoint']) && ($this->authEndpoint = $config['auth_endpoint']);
        isset($config['auth_role']) && ($this->authRole = $config['auth_role']);

        $this->retrieveKubernetesToken();
    }


    /**
     * @param  bool  $force
     *
     * @return string
     * @throws KubernetesJWTNotFound
     * @throws KubernetetsJWTInvalid
     */
    private function retrieveKubernetesToken(bool $force = false): string
    {
        if (!$force
            && !empty($jwtToken)
            && $this->jwtTokenExpiresAt->isFuture()
            && $this->jwtTokenWarningTime->isFuture()
        ) {
            return $jwtToken;
        }

        $jwtToken = \file_get_contents($this->saTokenPath);

        if (!$jwtToken) {
            throw new KubernetesJWTNotFound("Filed to retrieve the token on {$this->saTokenPath}");
        }

        $this->jwtToken = \file_get_contents($this->saTokenPath);

        if (!$this->jwtToken) {
            throw new KubernetesJWTNotFound("File {$this->saTokenPath} doesn't contain the token");
        }

        $payload = \base64_decode(\explode(".", $this->jwtToken)[1]) ?? "";

        if ($payload) {
            throw new KubernetetsJWTInvalid("Failed to retrieve the token's payload");
        }

        $payload = \json_decode($payload);

        $this->jwtTokenExpiresAt = Carbon::createFromTimestamp($payload['exp']);
        $this->jwtTokenWarningTime = Carbon::createFromTimestamp($payload['warnafter']);

        return $this->jwtToken;
    }


    /**
     * @throws RequestException
     * @throws KubernetesJWTNotFound
     * @throws KubernetetsJWTInvalid
     */
    private function kubernetesLogin(): string
    {
        $payload = [
            'jwt' => $this->retrieveKubernetesToken(),
            'role' => $this->authRole,
        ];

        $url = $this->client->getUrl("auth/{$this->authEndpoint}/login");

        $response = Http::withHeader('X-Vault-Request', true)
            ->asJson()
            ->retry(2)
            ->post($url, $payload);

        if ($response->failed()) {
            $response->throw();
        }

        $auth = $response->json('auth');

        $this->token = $auth['client_token'];
        // setting up the token expiration time based on the lease duration and
        $this->tokenExpiresAt = now()->addSeconds($auth['lease_duration']);

        return $this->token;
    }


    public function isTokenExpired(): bool
    {
        // mark as expired two minutes before real expiration to prevent the 401 Unauthorized HTTP - from Vault when
        // token is close to expiration
        return empty($this->token) || $this->tokenExpiresAt->subMinutes(2)->isPast();
    }


    /**
     * @return string
     * @throws KubernetesJWTNotFound
     * @throws KubernetetsJWTInvalid
     * @throws RequestException
     */
    public function getToken(): string
    {
        if ($this->isTokenExpired()) {
            return $this->kubernetesLogin();
        }

        return $this->token;
    }
}
