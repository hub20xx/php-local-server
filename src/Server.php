<?php

namespace PhpLocalServer;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class Server
 *
 * Manage PHP's built-in web server
 *
 * @package PhpLocalServer
 */
class Server
{
    /** @var string */
    private $docroot;

    /** @var string */
    private $address;

    /** @var string */
    private $port;

    /** @var string */
    private $pid;

    /** @var array */
    private $environmentVariables = [];

    /**
     * Create a new server, serving the contents of the docroot
     *
     * @param string $docroot
     * @param string $address
     * @param string $port
     */
    public function __construct($docroot, $address = '127.0.0.1', $port = '1111')
    {
        if (!is_dir($docroot)) {
            $message = 'Invalid docroot "' . $docroot . '"';
            throw new InvalidArgumentException($message);
        }

        $this->docroot = $docroot;
        $this->setAddress($address);
        $this->setPort($port);
    }

    /**
     * Set the server IP address
     *
     * @param string $address
     */
    public function setAddress($address)
    {
        if (!is_string($address)) {
            throw new InvalidArgumentException('Address must be a string');
        }

        if (!filter_var($address, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Address must be a valid IP address');
        }

        $this->address = $address;
    }

    /**
     * Set the server port
     *
     * @param string $port
     */
    public function setPort($port)
    {
        if (!is_string($port)) {
            throw new InvalidArgumentException('Port must be a string');
        }

        if ((int) $port < 1024 || (int) $port > 65535) {
            throw new InvalidArgumentException('Port must be a free port between 1024 and 65535');
        }

        $this->port = $port;
    }


    /**
     * Get the server IP address
     *
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Get the server port
     *
     * @return string
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Start the local server
     *
     * @throws RuntimeException
     */
    public function start()
    {
        // start the server without output and echo pid
        $command = sprintf(
            'php -d variables_order=EGPCS -S %s:%s -t %s > /dev/null 2>&1 & echo $!',
            $this->address,
            $this->port,
            $this->docroot
        );

        if (!empty($this->environmentVariables)) {
            foreach ($this->environmentVariables as $name => $value) {
                $command = $name . '=' . $value . ' ' . $command;
            }
        }

        // no need for exit code, as it is always 0 ('echo $!' always successful)
        exec($command, $output);

        // first element of $output should contain the pid
        if (!isset($output[0])) {
            throw new RuntimeException('Starting server not returning any pid');
        }

        $this->pid = $output[0];

        // wait a bit before checking if the pid exists
        // not waiting gives inconsistent results (false positives)
        usleep(200000); // 200 000 microseconds = 0.2 second

        // if there was an error with the command, the pid won't exist
        // on linux, running $ kill -0 <pid> checks if the pid exists
        // posix_kill is the php way of doing this
        if (!posix_kill($this->pid, 0)) {
            throw new RuntimeException('Failed starting server');
        }
    }

    /**
     * Stop the local server
     */
    public function stop()
    {
        // wait a bit before checking if the pid exists
        // not waiting gives inconsistent results (false positives)
        usleep(200000); // 200 000 microseconds = 0.2 second
        if ($this->pid !== null && posix_kill($this->pid, 0)) {
            exec('kill ' . $this->pid);
        }
    }

    /**
     * Stop the local server if it was not done explicitely
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Set the environment variable
     *
     * @param string $name
     * @param mixed $value
     */
    public function setEnvironmentVariable($name, $value)
    {
        if (!array_key_exists($name, $this->environmentVariables)) {
            $this->environmentVariables[$name] = $value;
        }
    }
}
