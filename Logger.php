<?php
/**
 * Arikaim
 *
 * @link        http://www.arikaim.com
 * @copyright   Copyright (c) Konstantin Atanasov <info@arikaim.com>
 * @license     http://www.arikaim.com/license
 * 
 */
namespace Arikaim\Core\Logger;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\HandlerInterface;
use Monolog\Formatter\NormalizerFormatter;

use Arikaim\Core\Utils\File;
use Arikaim\Core\Logger\JsonLogsFormatter;
use Arikaim\Core\Logger\LogsProcessor;
use Exception;

/**
 * Logger
 */
class Logger
{
    const DEFAULT_HANDLER = 'file';

    /**
     * Handler names
     *
     * @var array
     */
    private $handerNames = [
        'db',
        'file'
    ];

    /**
     * Logger object
     *
     * @var Monolog\Logger
     */
    protected $logger;
    
    /**
     * Enable/Disable logger
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Logs directory
     *
     * @var string
     */
    private $logsDir;

    /**
     * Current handler name
     *
     * @var string
     */
    private $handlerName;

    /**
     * Constructor
     *
     * @param string $logsDir
     * @param bool $enabled
     * @param string|null $handlerName
     */
    public function __construct($logsDir, $enabled, $handlerName = Self::DEFAULT_HANDLER) 
    {                
        $this->logsDir = $logsDir;      
        $this->enabled = $enabled;

        $this->logger = new MonologLogger('system');                
        $this->logger->pushProcessor(new LogsProcessor());  

        $handlerName = ($this->isValidHandlerName($handlerName) == true) ? $handlerName : Self::DEFAULT_HANDLER;
        $this->setHandler($handlerName);
    }

    /**
     * Return true if handler name is valid
     *
     * @param string $name
     * @return boolean
     */
    public function isValidHandlerName($name)
    {
        return \in_array($name,$this->handerNames);
    }

    /**
     * Get handler name
     *
     * @return string
     */
    public function getHandelerName()
    {
        return $this->handlerName;
    }

    /**
     *  Get handler names
     */
    public function getHandlerNames()
    {
        return $this->handerNames;
    }

    /**
     * Create handler instance
     *
     * @param string $name
     * @return HandlerInterface
     * @throws Exception
     */
    public function createHandler($name): HandlerInterface
    {
        switch($name) {
            case 'file':
                $handler = new \Monolog\Handler\StreamHandler($this->logsDir,MonologLogger::DEBUG); 
                $formatter = new JsonLogsFormatter();
                $handler->setFormatter($formatter); 
                break;

            case 'db':
                $handler = new \Arikaim\Core\Logger\Handler\DbHandler(); 
                $formatter = new NormalizerFormatter();
                $handler->setFormatter($formatter); 
                break;

            default:
                throw new Exception('Not valid log handler name', 1);   
        }

        return $handler;
    }

    /**
     * Replace all handlers with one
     *
     * @param string $name
     * @return void
     */
    public function setHandler($name)
    {
        $handler = $this->createHandler($name);
        $this->handlerName = $name;

        $this->setHandlers([$handler]);
    }

    /**
     * Set handlers
     *
     * @param array $handlers
     * @return void
     */
    public function setHandlers(array $handlers)
    {
        $this->logger->setHandlers($handlers);
    }

    /**
     * Push handler
     *
     * @param string $name
     * @return void
     */
    public function pushHandler($name)
    {
        $handler = $this->createHandler($name);

        $this->logger->pushHandler($handler);
    }

    /** Pop handler
     *
     * @return HandlerInterface
     */
    public function popHandler(): HandlerInterface
    {
        return $this->logger->popHandler();
    }

    /**
     * Disable logger
     *
     * @return void
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * Get handler
     *
     * @return array
     */
    public function getHandlers()
    {
        return $this->logger->getHandlers();
    }

    /**
     * Get logs file name
     *
     * @return string
     */
    public function getLogsFileName()
    {
        return $this->logsDir;
    }

    /**
     * Delete logs file
     *
     * @return bool
     */
    public function deleteSystemLogs()
    {
        $handlers = $this->getHandlers();

        foreach ($handlers as $handler) {
            $this->deleteLogs($handler);
        }  
    }

    /**
     * Delete logs
     *
     * @param object $handler
     * @return bool
     */
    protected function deleteLogs($handler)
    {     
        switch (\get_class($handler)) {
            case 'Monolog\Handler\StreamHandler':
                return (File::exists($this->getLogsFileName()) == false) ? true : File::delete($this->getLogsFileName());
            
            case 'Arikaim\Core\Logger\Handler\DbHandler': {
                return $handler->getLogsStorage()->getQuery()->delete();
            }            
        }
        
        return false;
    }

    /**
     * Read logs file with paginator
     *
     * @return void
     */
    public function readSystemLogs()
    {       
        $text = '[' . File::read($this->getLogsFileName());      
        $text = \rtrim($text,",\n");
        $text .= "]\n";

        $logs = \json_decode($text,true);
      
        return $logs;
    }

    /**
     * Call logger function
     *
     * @param string $name
     * @param mixed $arguments
     * @return boolean
     */
    public function __call($name, $arguments)
    {           
        $message = $arguments[0] ?? '';
        $context = $arguments[1] ?? [];

        return ($this->enabled == true) ? $this->logger->{$name}($message,$context) : false;          
    }
    
    /**
     * Add log record
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return boolean
     */
    public function log($level, $message, array $context = [])
    {   
        return ($this->enabled == true) ? $this->logger->log($level,$message,$context) : false;        
    } 

    /**
     * Add error log
     *
     * @param string $message
     * @param array $context
     * @return boolean
     */
    public function error($message, array $context = [])
    {      
        return ($this->enabled == true) ? $this->logger->error($message,$context) : false;      
    }

    /**
     * Add info log
     *
     * @param string $message
     * @param array $context
     * @return boolean
    */
    public function info($message, array $context = [])
    {
        return ($this->enabled == true) ? $this->logger->info($message,$context) : false; 
    }

    /**
     * Return stats logger 
     *
     * @return Monolog\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set logger
     *
     * @param Monolog\Logger $logger
     * @return void
     */
    public function setLogger($logger)
    {
        return $this->logger = $logger;
    }
}
