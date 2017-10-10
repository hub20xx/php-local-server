<?php

use PhpLocalServer\Server;
use PHPUnit\Framework\TestCase;

class ServerTest extends TestCase
{
    private $statusCode;
    private $testCaseServerPid;
    private $docroot = '/tmp/local-php-server-test';

    public function setUp()
    {
        parent::setUp();
        $this->createDocroot();
    }

    private function createDocroot()
    {
        if (!file_exists($this->docroot)) {
            if (!mkdir($this->docroot, 0777, true)) {
                throw new RuntimeException('Could not create docroot (path: "' . $this->docroot . '")');
            }
        }
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->stopTestCaseServer();
        $this->removeDocroot();
    }

    private function stopTestCaseServer()
    {
        if ($this->testCaseServerPid !== null && posix_kill($this->testCaseServerPid, 0)) {
            exec('kill ' . $this->testCaseServerPid);
        }
    }

    private function removeDocroot()
    {
        if (file_exists($this->docroot) && is_dir($this->docroot)) {
            $command = sprintf('rm -rf %s', $this->docroot);
            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException('Failed removing Docroot');
            }
        }
    }

    /** @test */
    public function it_requires_a_docroot()
    {
        $docroot = '/unexisting/path/';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid docroot "' . $docroot . '"');

        new Server($docroot);
    }


    /** @test */
    public function docroot_must_be_a_folder()
    {
        $docroot = __FILE__;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid docroot "' . $docroot . '"');

        new Server($docroot);
    }

    /** @test */
    public function it_is_set_to_run_on_127_0_0_1_by_default()
    {
        $server = new Server($this->docroot);

        $address = $server->getAddress();

        $this->assertEquals('127.0.0.1', $address);
    }

    /** @test */
    public function it_is_set_to_run_on_port_1111_by_default()
    {
        $server = new Server($this->docroot);

        $port = $server->getPort();

        $this->assertEquals('1111', $port);
    }

    /** @test */
    public function address_can_be_set_on_instantiation()
    {
        $address = '127.0.0.2';
        $server = new Server($this->docroot, $address);

        $addr = $server->getAddress();

        $this->assertEquals($address, $addr);
    }

    /** @test */
    public function address_can_be_set_after_instantiation()
    {
        $server = new Server($this->docroot);
        $address = '127.0.0.2';
        $server->setAddress($address);

        $addr = $server->getAddress();

        $this->assertEquals($address, $addr);
    }

    /** @test */
    public function it_requires_the_address_as_a_string()
    {
        $invalidAddress = 1234.23423;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address must be a string');

        new Server($this->docroot, $invalidAddress);
    }

    /** @test */
    public function it_requires_a_valid_ip_address()
    {
        $invalidAddress = '1234.23423.234123.234';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address must be a valid IP address');

        new Server($this->docroot, $invalidAddress);
    }

    /** @test */
    public function it_requires_the_port_as_a_string()
    {
        $address = '127.0.0.1';
        $invalidPort = 1234;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Port must be a string');

        new Server($this->docroot, $address, $invalidPort);
    }

    /** @test */
    public function it_requires_a_free_port_between_1024_and_65535()
    {
        $address = '127.0.0.1';
        $invalidPort = '22';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Port must be a free port between 1024 and 65535');

        new Server($this->docroot, $address, $invalidPort);
    }

    /** @test */
    public function it_throws_an_exception_if_the_address_port_is_already_in_use()
    {
        $server = new Server($this->docroot);

        $this->startTestCaseServer('127.0.0.1', '1111');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed starting server');

        $server->start();

        $this->stopTestCaseServer();
    }

    private function startTestCaseServer($address, $port)
    {
        // start the server without output and echo pid
        $command = sprintf(
            'php -S %s:%s > /dev/null 2>&1 & echo $!',
            $address,
            $port
        );

        // no need for exit code, as it is always 0 ('echo $!' always successful)
        exec($command, $output);

        // first element of $output should contain the pid
        if (!isset($output[0])) {
            throw new RuntimeException('Starting TestCase server not returning any pid');
        }

        $this->testCaseServerPid = $output[0];

        // wait a bit before checking if the pid exists
        // not waiting gives inconsistent results (false positives)
        usleep(200000); // 200 000 microseconds = 0.2 second

        // if there was an error with the command, the pid won't exist
        // on linux, running $ kill -0 <pid> checks if the pid exists
        // posix_kill is the php way of doing this
        if (!posix_kill($this->testCaseServerPid, 0)) {
            throw new RuntimeException('Failed starting TestCase server');
        }
    }

    /** @test */
    public function it_starts_in_the_given_docroot_and_serves_the_content()
    {
        $filename = 'test.php';
        $data = 'testing local server';
        $this->createFileInDocRoot($filename, $data);
        $url = 'http://127.0.0.1:1111/test.php';
        $server = new Server($this->docroot);

        $server->start();
        $response = $this->get($url);

        $this->assertEquals($data, $response);
        $this->assertEquals(200, $this->statusCode);
    }

    private function createFileInDocRoot($filename, $data)
    {
        $filename = $this->docroot . '/' . $filename;
        if (!file_put_contents($filename, $data)) {
            throw new RuntimeException('Could not create file (filename: "' . $filename . '")');
        }
    }

    private function get($url)
    {
        $curlHandler = curl_init($url);
        curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curlHandler);
        $this->statusCode = curl_getinfo($curlHandler, CURLINFO_HTTP_CODE);

        return $response;
    }

    /** @test */
    public function it_can_be_stopped()
    {
        $server = new Server($this->docroot);
        $server->start();
        $this->createFileInDocRoot('index.php', 'index');
        $url = 'http://127.0.0.1:1111/index.php';
        $this->get($url);

        $this->assertEquals(200, $this->statusCode);

        $server->stop();
        $this->assertFalse($this->get($url));
    }

    /** @test */
    public function it_stops_automatically_when_it_is_destroyed()
    {
        $server = new Server($this->docroot);
        $server->start();
        $this->createFileInDocRoot('index.php', 'index');
        $url = 'http://127.0.0.1:1111/index.php';
        $this->get($url);

        $this->assertEquals(200, $this->statusCode);

        $server = null;
        $this->assertFalse($this->get($url));
    }

    /** @test */
    public function it_sets_environment_variables()
    {
        $server = new Server($this->docroot);
        $server->setEnvironmentVariable('ENV', 'testing');
        $server->setEnvironmentVariable('FOO', 'bar');
        $server->start();
        $this->createFileInDocRoot('index.php', '<?php echo \'ENV is \' . $_ENV[\'ENV\'] . \', FOO is \' . $_ENV[\'FOO\']; ?>');
        $expected = 'ENV is testing, FOO is bar';
        $url = 'http://127.0.0.1:1111/index.php';
        $response = $this->get($url);

        $this->assertEquals($expected, $response);
    }
}
