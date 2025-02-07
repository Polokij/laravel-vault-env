<?php

namespace LaravelVault\AuthHandlers;

use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LaravelVault\Exceptions\KubernetesJWTNotFound;
use LaravelVault\Exceptions\KubernetesJWTInvalid;
use LaravelVault\Vault;

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
     * @param  Vault  $client
     * @param  array        $config
     *
     * @throws KubernetesJWTNotFound
     * @throws KubernetesJWTInvalid
     */
    public function __construct(
        protected Vault $client,
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
     * @throws KubernetesJWTInvalid
     */
    private function retrieveKubernetesToken(bool $force = false): string
    {
        if (!$force
            && !empty($jwtToken)
            && $this->jwtTokenExpiresAt->isFuture()
            // && $this->jwtTokenWarningTime->isFuture()
        ) {
            return $jwtToken;
        }

        if (!\file_exists($this->saTokenPath)) {
            throw new KubernetesJWTNotFound("File with JWT {$this->saTokenPath} not found");
        }

        $this->jwtToken = \file_get_contents($this->saTokenPath);

        if (!$this->jwtToken) {
            throw new KubernetesJWTInvalid("Kubernetes JWT is invalid");
        }

        $jwtParts = \explode(".", $this->jwtToken);

        if (\count($jwtParts) !== 3) {
            throw new KubernetesJWTInvalid("Failed to retrieve the token's payload");
        }

        $payload = json_decode(\base64_decode($jwtParts[1]), true);

        if (!$payload) {
            throw new KubernetesJWTInvalid("Failed to retrieve the token's payload");
        }

        $this->jwtTokenExpiresAt = Carbon::createFromTimestamp($payload['exp']);
        $this->jwtTokenWarningTime = Carbon::createFromTimestamp($payload['kubernetes.io']['warnafter']);

        return $this->jwtToken;
    }


    /**
     * @throws RequestException
     * @throws KubernetesJWTNotFound
     * @throws KubernetesJWTInvalid
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
        Log::debug('The Vault token will expire at '.$this->tokenExpiresAt);

        return $this->token;
    }


    /**
     * @return bool
     */
    public function isTokenExpired(): bool
    {
        // mark as expired two minutes before real expiration to prevent the 401 Unauthorized HTTP - from Vault when
        // token is close to expiration
        return empty($this->token) || $this->tokenExpiresAt->subMinutes(2)->isPast();
    }


    /**
     * @return string
     * @throws KubernetesJWTNotFound
     * @throws KubernetesJWTInvalid
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
