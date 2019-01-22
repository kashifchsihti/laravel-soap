<?php

namespace Artisaninweb\SoapWrapper;

use Artisaninweb\SoapWrapper\Exceptions\ServiceAlreadyExists;
use Artisaninweb\SoapWrapper\Exceptions\ServiceMethodNotExists;
use Artisaninweb\SoapWrapper\Exceptions\ServiceNotFound;
use Closure;

class SoapWrapper
{
    /**
     * @var array
     */
    protected $services;

    /**
     * SoapWrapper constructor.
     */
    public function __construct()
    {
        $this->services = [];
    }

    /**
     * Add a new service to the wrapper.
     *
     * @param string  $name
     * @param Closure $closure
     *
     * @throws ServiceAlreadyExists
     *
     * @return $this
     */
    public function add($name, Closure $closure)
    {
        if (!$this->has($name)) {
            $service = new Service();

            $closure($service);

            $this->services[$name] = $service;

            return $this;
        }

        throw new ServiceAlreadyExists("Service '".$name."' already exists.");
    }

    /**
     * Add services by array.
     *
     * @param array $services
     *
     * @throws ServiceAlreadyExists
     * @throws ServiceMethodNotExists
     *
     * @return $this
     */
    public function addByArray(array $services = [])
    {
        if (!empty($services)) {
            foreach ($services as $name => $methods) {
                if (!$this->has($name)) {
                    $service = new Service();

                    foreach ($methods as $method => $value) {
                        if (method_exists($service, $method)) {
                            $service->{$method}($value);
                        } else {
                            throw new ServiceMethodNotExists(sprintf(
                  "Method '%s' does not exists on the %s service.",
                  $method,
                  $name
                ));
                        }
                    }

                    $this->services[$name] = $service;

                    continue;
                }

                throw new ServiceAlreadyExists(sprintf(
          "Service '%s' already exists.",
          $name
        ));
            }
        }

        return $this;
    }

    /**
     * Get the client.
     *
     * @param string  $name
     * @param Closure $closure
     *
     * @throws ServiceNotFound
     *
     * @return mixed
     */
    public function client($name, Closure $closure = null)
    {
        if ($this->has($name)) {
            /** @var Service $service */
            $service = $this->services[$name];

            if (is_null($service->getClient())) {
                $client = new Client($service->getWsdl(), $service->getOptions());
            } else {
                $client = $service->getClient();
            }

            return $closure($client);
        }

        throw new ServiceNotFound("Service '".$name."' not found.");
    }

    /**
     * A easy access call method.
     *
     * @param string $call
     * @param array  $data
     *
     * @return mixed
     */
    public function call($call, $data = [])
    {
        list($name, $function) = explode('.', $call, 2);

        return $this->client($name, function ($client) use ($function, $data) {
            /* @var Client $client */
            return $client->SoapCall($function, $data);
        });
    }

    /**
     * Check if wrapper has service.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->services);
    }
}
