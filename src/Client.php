<?php
namespace Zulip;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Zulip\Request\MessageParameters;
use Zulip\Request\ParametersInterface;
use Zulip\Request\RequestInterface;
use Zulip\Request\ValidationException;

class Client implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var ClientInterface
     */
    private $client;
    /**
     * @var Authentication
     */
    private $defaultAuthentication;
    /**
     * @var
     */
    private $serverUrl;

    public function __construct($serverUrl, LoggerInterface $logger = null)
    {
        $this->setLogger(is_null($logger) ? new NullLogger() : $logger);
        $this->serverUrl = $serverUrl;
    }

    /**
     * @param Authentication $defaultAuthentication
     */
    public function setDefaultAuthentication($defaultAuthentication)
    {
        $this->defaultAuthentication = $defaultAuthentication;
    }

    /**
     * Send message to zulip
     *
     * @param array|MessageParameters $messageParameters
     * @return mixed
     * @throws ValidationException
     * @throws \Exception
     */
    public function sendMessage($messageParameters)
    {
        if (is_array($messageParameters)) {
            $messageParameters = new MessageParameters($messageParameters);
        }

        if (!$messageParameters instanceof MessageParameters) {
            throw new \BadMethodCallException('$messageParameter must be an instance of MessageParameters or an array');
        }

        return $this->createRequest('\Zulip\Request\MessageRequest', $messageParameters);
    }

    /**
     * @return ClientInterface
     */
    public function getHttpClient()
    {
        if (empty($this->client)) {
            $this->setHttpClient(new \GuzzleHttp\Client());
        }

        return $this->client;
    }

    /**
     * @param ClientInterface $client
     */
    public function setHttpClient($client)
    {
        $this->client = $client;
    }

    protected function createRequest($class, ParametersInterface $parameters)
    {
        $this->logger->info("Sending request type: '" . $class. "", ['params' => $parameters->getData()]);

        $authentication = $parameters->getAuthentication();
        if (is_null($authentication)) {
            $authentication = $this->defaultAuthentication;
        }

        if (!$authentication instanceof Authentication) {
            throw new \Exception('Missing authentication');
        }

        try {
            /** @var RequestInterface $request */
            $request = new $class($this->getHttpClient());
            return $request->initialize($this->serverUrl, $parameters, $authentication);
        }catch(ValidationException $e) {
            $this->logger->error("Error during validation", [
                'message' => $e->getMessage(),
                'errors'  => $e->getErrors(),
                'params'  => $parameters->getData()
            ]);

            throw $e;
        }catch(\Exception $e) {
            $this->logger->error('Exception occurred', [
                'message' => $e->getMessage(),
                'params'  => $parameters->getData()
            ]);

            throw $e;
        }
    }
}