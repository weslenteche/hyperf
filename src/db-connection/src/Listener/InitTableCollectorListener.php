<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\DbConnection\Listener;

use Hyperf\Command\Event\BeforeHandle;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\DbConnection\Collector\TableCollector;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\AfterWorkerStart;
use Hyperf\Process\Event\BeforeProcessHandle;

class InitTableCollectorListener implements ListenerInterface
{
    const DB_TO_FUNCTION = [
        'COLUMN_NAME' => 'setName',
        'ORDINAL_POSITION' => 'setPosition',
        'COLUMN_DEFAULT' => 'setDefault',
        'DATA_TYPE' => 'setType',
        'IS_NULLABLE' => 'setIsNull',
    ];

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var TableCollector
     */
    protected $collector;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $container->get(ConfigInterface::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->collector = $container->get(TableCollector::class);
    }

    public function listen(): array
    {
        return [
            BeforeHandle::class,
            AfterWorkerStart::class,
            BeforeProcessHandle::class,
        ];
    }

    public function process(object $event)
    {
        try {
            $databases = $this->config->get('database', []);
            $pools = array_keys($databases);
            foreach ($pools as $name) {
                $this->initTableCollector($name);
            }
        } catch (\Throwable $throwable) {
            $this->logger->error((string) $throwable);
        }
    }

    public function initTableCollector($pool)
    {
        if ($this->collector->has($pool)) {
            return;
        }

        $connection = $this->container->get(ConnectionResolverInterface::class)->connection($pool);

        $columns = $connection->getSchemaBuilder()->getColumns();

        foreach ($columns as $column) {
            $this->collector->add($pool, $column);
        }
    }
}
