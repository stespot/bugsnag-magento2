<?php
/**
 * Handler to send messages to BugSnag
 * using bugsnag-php (https://github.com/bugsnag/bugsnag-php)
 *
 * @author Josh Carter <josh@webtise.com>
 */
namespace Webtise\BugSnag\Bugsnag;

use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Bugsnag\Client as Bugsnag_Client;
use Bugsnag\Report;

class Handler extends AbstractProcessingHandler
{
    /**
     * Translates Monolog log levels to Raven log levels.
     */
    private $logLevels = array(
        Logger::DEBUG     => 'info',
        Logger::INFO      => 'info',
        Logger::NOTICE    => 'info',
        Logger::WARNING   => 'warning',
        Logger::ERROR     => 'error',
        Logger::CRITICAL  => 'error',
        Logger::ALERT     => 'error',
        Logger::EMERGENCY => 'error',
    );

    /**
     * @var Client the client object that sends the message to the server
     */
    protected $bugsnagClient;

    /**
     * @param Client $bugsnagClient
     * @param integer      $level       The minimum logging level at which this handler will be triggered
     * @param Boolean      $bubble      Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Bugsnag_Client $bugsnagClient, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->bugsnagClient = $bugsnagClient;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $severity = $this->getSeverity($record['level']);
        if (isset($record['context']['exception'])) {
            $this->bugsnagClient->notifyException(
                $record['context']['exception'],
                function (Report $report) use ($record, $severity) {
                    $report->setSeverity($severity);
                    if (isset($record['extra'])) {
                        $report->setMetaData($record['extra']);
                    }
                }
            );
        } else {
            $this->bugsnagClient->notifyError(
                (string) $record['message'],
                (string) $record['formatted'],
                function (Report $report) use ($record, $severity) {
                    $report->setSeverity($severity);
                    if (isset($record['extra'])) {
                        $report->setMetaData($record['extra']);
                    }
                }
            );
        }
    }

    /**
     * Translates monolog error to bugsnag severity
     *
     * @param int $errorCode - one of the Logger:: constants.
     * @return string
     */
    protected function getSeverity($errorCode)
    {
        if (isset($this->logLevels[$errorCode])) {
            return $this->logLevels[$errorCode];
        } else {
            return $this->logLevels[Logger::ERROR];
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new LineFormatter('[%channel%] %message%');
    }

    /**
     * Gets the default formatter for the logs generated by handleBatch().
     *
     * @return FormatterInterface
     */
    protected function getDefaultBatchFormatter()
    {
        return new LineFormatter();
    }
}
