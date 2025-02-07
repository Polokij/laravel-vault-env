<?php

namespace LaravelVault;

use Illuminate\Http\Client\{PendingRequest, RequestException, Response};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LaravelVault\AuthHandlers\{AuthContract, KubernetesAuth, TokenAuth};
use LaravelVault\Enums\Action;
use LaravelVault\Enums\VaultAuthType;
use LaravelVault\Exceptions\KubernetesJWTInvalid;
use LaravelVault\Exceptions\KubernetesJWTNotFound;

/**
 * Class Vault API client
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package LaravelVault
 * @property-read Vault $auth
 * @property-read Vault $sys
 */
class Vault
{
    /**
     * @var array
     */
    private array $headers
        = [
            'X-Vault-Request' => true,
            'Content-Type'    => 'application/json',
        ];

    /**
     * @var bool
     */
    private bool $throwException = true;

    /**
     * @var string
     */
    private string $apiVersion = 'v1';

    /**
     * @var string
     */
    private string $apiEndpoint = '';

    /**
     * @var string
     */
    private string $policyTemplate = '';

    /**
     * @var int
     */
    private int $retries = 1;

    /**
     * @var int
     */
    private int $timeout = 1;

    /**
     * @var Response
     */
    private Response $response;

    /**
     * @var array
     */
    private array $options = [];

    /**
     * @var VaultAuthType
     */
    private VaultAuthType $authType = VaultAuthType::TOKEN;

    private AuthContract $authResolver;


    /**
     * @param string $address
     * @param string $storage
     * @param string $prefix
     * @param string $token
     */
    public function __construct(
        private string $address = '',
        private string $storage = 'secrets',
        private string $prefix = '',
        #[\SensitiveParameter] private $token = ''
    ) {
        if ($token) {
            $this->setToken($token);
        }
    }


    /**
     * @param VaultAuthType $authType
     * @param array $config
     *
     * @return AuthContract
     * @throws KubernetesJWTNotFound
     * @throws KubernetesJWTInvalid
     */
    public function setAuth(VaultAuthType $authType, array $config): AuthContract
    {
        return $this->authResolver = match ($authType) {
            VaultAuthType::KUBERNETES => new KubernetesAuth($this, $config),
            VaultAuthType::TOKEN => new TokenAuth($this, $config),
        };
    }

    /**
     * @return string
     */
    public function getStorage(): string
    {
        return $this->storage;
    }


    /**
     * @param mixed $token
     */
    public function setToken($token): Vault
    {
        $this->token = $token;
        $this->headers['X-Vault-Token'] = "$this->token";

        return $this;
    }


    /**
     * @return $this
     */
    public function instance(): self
    {
        return $this;
    }


    /**
     * @return array|mixed
     * @throws RequestException
     */
    public function status(): mixed
    {
        return $this->sys->get('seal-status');
    }


    /**
     * @param $path
     *
     * @return array|mixed
     * @throws RequestException
     */
    public function get(string $key): mixed
    {
        return $this->processRequest(fn(string $path, PendingRequest $request) => $request->get($path), $key);
    }


    /**
     * @param \Closure $method
     * @param string $url
     *
     * @return array|mixed
     * @throws RequestException
     */
    private function processRequest(\Closure $method, string $url): mixed
    {
        if (isset($this->authResolver)) {
            $this->setToken($this->authResolver->getToken());
        }

        $url = $this->getUrl($url);

        $request = Http::withHeaders($this->headers)
            ->timeout($this->timeout)
            ->accept('application/json')
            ->withOptions($this->options);

        if ($this->retries > 1) {
            $request->retry($this->retries, 10, throw: false);
        }

        $this->response = $method($url, $request);

        $this->apiEndpoint = '';

        if (!$this->response->successful()) {
            if ($this->throwException) {
                $this->response->throw();
            }

            return $this->response->json();
        }

        $payload = $this->response->json();

        return $payload['data'] ?? $payload;
    }


    /**
     * @param $key
     *
     * @return string
     * @deprecated Use getUrl() method instead of getPath()
     *
     */
    public function getPath($key): string
    {
        return $this->getUrl($key);
    }

    /**
     * @param $key
     *
     * @return string
     */
    public function getUrl($key): string
    {
        $path = "{$this->address}/{$this->apiVersion}";

        if ($this->apiEndpoint) {
            $path .= "/{$this->apiEndpoint}";
        }

        if ($key) {
            $path .= "/".trim($key, '/ ');
        }

        return $path;
    }


    /**
     * @param string $key
     * @param bool $reset
     *
     * @return array|mixed
     * @throws RequestException
     */
    public function seal(): mixed
    {
        return $this->sys->post('seal');
    }


    /**
     * @param string $key
     * @param array $params
     *
     * @return array|mixed
     * @throws RequestException
     */
    public function post(string $key, array $params = []): mixed
    {
        return $this->processRequest(fn(string $path, PendingRequest $request) => $request->post($path, $params), $key);
    }


    /**
     * Initialize the Vault
     */
    public function init(array $initOptions = []): array
    {
        $initOptions = \array_merge($initOptions, [
            'secret_shares'    => 4,
            'secret_threshold' => 2,
        ]);
        $response = $this->sys->post('init', $initOptions);

        return $response;
    }


    /**
     * @param string $key
     * @param bool $reset
     *
     * @return array|mixed
     * @throws RequestException
     */
    public function unseal(string $key = '', bool $reset = false): mixed
    {
        $params = [
            'reset'   => $reset,
            'migrate' => false,
        ];

        $params += $key ? ['key' => $key,] : [];

        return $this->sys->put('unseal', $params);
    }


    /**
     * @param string $key
     * @param array $params
     *
     * @return array|mixed
     * @throws RequestException
     */
    public function put(string $key, array $params = []): mixed
    {
        return $this->processRequest(fn(string $path, PendingRequest $request) => $request->put($path, $params), $key);
    }


    /**
     * @return array|mixed
     * @throws RequestException
     */
    public function tokenLookup(string $token = ''): mixed
    {
        if (!$token) {
            return $this->auth->get('token/lookup-self');
        } else {
            return $this->auth->post('token/lookup', ['token' => $token]);
        }
    }


    /**
     * @return array|mixed
     * @throws RequestException
     */
    public function tokenAccessors(): mixed
    {
        return $this->auth->listRequest('/token/accessors');
    }


    /**
     * @param $path
     *
     * @return array|mixed
     * @throws RequestException
     */
    public function listRequest(string $key): mixed
    {
        return $this->processRequest(fn(string $path, PendingRequest $request) => $request->send('LIST', $path), $key);
    }


    /**
     * @return array|mixed
     * @throws RequestException
     */
    public function tokenCreate(
        array $policies,
        bool $renewable = true,
        array $meta = [],
        string $displayName = '',
        string $type = 'service'
    ): mixed {
        $params = collect(get_defined_vars())
            ->filter(fn($value) => !!$value)
            ->mapWithKeys(fn($value, $key) => [Str::snake($key) => $value]);

        return $this->auth->post('token/create', $params->toArray());
    }


    /**
     * @param string $token
     *
     * @return array|mixed
     * @throws RequestException
     */
    public function tokenRenew(string $token = ''): mixed
    {
        if (!$token) {
            return $this->auth->post('token/renew-self', []);
        } else {
            return $this->auth->post('token/renew', ['token' => $token]);
        }
    }


    /**
     * Resources has format ["resourceKey": ["action1", "action2"]]
     *
     * @return array|mixed
     * @throws RequestException
     */
    public function policyCreate(string $name, array $resources): mixed
    {
        $policy = [
            'policy' => $this->serializePolicy($resources),
        ];

        return $this->sys->post("policy/$name", $policy);
    }


    /**
     * @param array $resources
     *
     * @return string
     */
    public function serializePolicy(array $resources): string
    {
        return collect($resources)->map(function (array $actions, $key) {
            $actions = collect($actions)->implode(
                fn(Action $action) => Str::wrap($action->name, '"'), //adding the double quotes
                ", ");

            $path = Str::wrap(trim($key, '/ '), '"');
            $policy = Str::replace('#PATH', $path, $this->policyTemplate);

            return Str::replace('#ACTIONS', $actions, $policy);
        })->implode("\n\n");
    }


    /**
     * @return array|mixed
     * @throws RequestException
     */
    public function policy(string $name = ''): mixed
    {
        return $this->sys->get("policy/$name");
    }


    /**
     * @return array|mixed
     * @throws RequestException
     */
    public function policyDelete(string $name): mixed
    {
        return $this->sys->delete("policy/$name");
    }


    /**
     * @param string $key
     * @param array $params
     *
     * @return array|mixed
     * @throws RequestException
     */
    public function delete(string $key): mixed
    {
        return $this->processRequest(fn(string $path, PendingRequest $request) => $request->delete($path), $key);
    }


    /**
     * @param string $key
     * @param null $value
     * @param array $metadata
     *
     * @return mixed
     * @throws RequestException
     */
    public function secret(string $key = '', $value = null, array $metadata = []): mixed
    {
        $path = $this->getSecretPath($key);

        if ($value === null) {
            $secretResponse = $this->get($path);

            return $secretResponse['data'] ?? $secretResponse;
        }

        $secretPostBody = $this->post($path, ['data' => $value]);

        if ($this->getResponse()->successful() && $metadata) {
            $metadataKey = \Str::replaceFirst('data', 'metadata', $path);

            $metadataUpdateBody = $this->post($metadataKey, ['custom_metadata' => $metadata]);

            return $metadataUpdateBody;
        }

        return $secretPostBody;
    }


    /**
     * @param string $key
     * @param string $dest
     *
     * @return string
     */
    public function getSecretPath(string $key, string $dest = 'data'): string
    {
        $key = trim($key, '/ ');

        return "{$this->storage}/$dest/".($this->prefix ? "{$this->prefix}/$key" : "$key");
    }


    /**
     * @param string $key
     *
     * @return mixed
     * @throws RequestException
     */
    public function secretsList(string $key = ''): mixed
    {
        return $this->listRequest($this->getSecretPath($key, 'metadata'));
    }


    /**
     * @param string $key
     *
     * @return mixed
     * @throws RequestException
     */
    public function secretDestroy(string $key = ''): mixed
    {
        return $this->delete($this->getSecretPath($key, 'metadata'));
    }


    /**
     * @param string $key
     *
     * @return bool
     * @throws RequestException
     */
    public function destroyRecursive(string $key): bool
    {
        $this->setThrowException(false);
        $key = rtrim($key, '/');
        collect($this->secretsList($key)['keys'] ?? [])
            ->each(function ($nestedKey) use ($key) {
                if (Str::endsWith($nestedKey, '/')) {
                    $this->destroyRecursive("$key/$nestedKey");

                    return;
                }

                $this->secretDestroy("$key/$nestedKey");
            });

        $result = $this->secretDestroy($key);

        return !isset($result['errors']);
    }


    /**
     * Alias for destroyRecursive method
     *
     * @param string $key
     *
     * @return bool
     * @throws RequestException
     */
    public function secretDestroyRecursive(string $key = ''): bool
    {
        return $this->destroyRecursive($key);
    }


    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }


    /**
     * @param string $key
     *
     * @return $this|string
     */
    public function __get(string $key)
    {
        if ($key === 'path') {
            return $this->getUrl('');
        }

        $this->apiEndpoint = $key;

        return $this;
    }


    /**
     * @return bool
     */
    public function isThrowException(): bool
    {
        return $this->throwException;
    }


    /**
     * @param bool $throwException
     *
     * @return void
     */
    public function setThrowException(bool $throwException): self
    {
        $this->throwException = $throwException;

        return $this;
    }


    /**
     * @return string
     */
    public function getPolicyTemplate(): string
    {
        return $this->policyTemplate;
    }


    /**
     * @param string $policyTemplate
     *
     * @return void
     */
    public function setPolicyTemplate(string $policyTemplate): self
    {
        $this->policyTemplate = $policyTemplate;

        return $this;
    }


    /**
     * @return string
     */
    public function getTimeout(): string
    {
        return $this->timeout;
    }


    /**
     * @param string $timeout
     *
     * @return void
     */
    public function setTimeout(string $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }


    /**
     * @param array $options
     *
     * @return void
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }


    /**
     * @return int
     */
    public function getRetries(): int
    {
        return $this->retries;
    }


    /**
     * @param int $retries
     *
     * @return $this
     */
    public function setRetries(int $retries): self
    {
        $this->retries = $retries;

        return $this;
    }
}
