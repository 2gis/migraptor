<?php

class MigraptorException extends Exception
{
    const COLOR = CLIHelper::COLOR_WARNING;
    const IS_FATAL = false;
}

class MigraptorWarningException extends MigraptorException
{
    const COLOR = CLIHelper::COLOR_WARNING;
    const IS_FATAL = false;
}

class MigraptorErrorException extends MigraptorException
{
    const COLOR = CLIHelper::COLOR_ERROR;
    const IS_FATAL = false;
}

class MigraptorFatalException extends MigraptorException
{
    const COLOR = CLIHelper::COLOR_FATAL;
    const IS_FATAL = true;
}

class MigraptorValidationException extends MigraptorFatalException
{
}

class MigraptorCommand extends CConsoleCommand
{
    const BEHAVIOR_SQL = 'sql';
    const BEHAVIOR_YII = 'yii';

    const FILTER_TYPE_GREATER = 'greater';
    const FILTER_TYPE_LOWER = 'lower';
    const FILTER_TYPE_ALL = 'all';

    const ACTION_UP = 'up';
    const ACTION_DOWN = 'down';

    const MAX_LIMIT = PHP_INT_MAX;

    private $isDone = false;
    private $_params = [];
    protected $_skipConnections = [];
    protected $_migrationsBasePath = [];
    protected $_projectMigrationTypes = [];
    protected $_migrationTypes = [];
    protected $_defaultMigrationTypes = [
        'functions' => [
            'description' => 'stored procedures and functions',
            'behavior' => self::BEHAVIOR_SQL,
            'execute_always' => true,
            'need_up_down' => false,
            'allow_any_name' => true,
        ],
        'views' => [
            'description' => 'views',
            'behavior' => self::BEHAVIOR_SQL,
            'execute_always' => true,
            'need_up_down' => false,
            'allow_any_name' => true,
        ],
        'structures' => [
            'description' => 'database DDL',
            'behavior' => self::BEHAVIOR_SQL,
            'execute_always' => false,
            'need_up_down' => true,
            'allow_any_name' => false,
        ],
        'datas' => [
            'description' => 'database DML',
            'behavior' => self::BEHAVIOR_SQL,
            'execute_always' => false,
            'need_up_down' => true,
            'allow_any_name' => false,
        ],
        'migrations' => [
            'description' => 'yii migrations (php classes)',
            'behavior' => self::BEHAVIOR_YII,
            'execute_always' => false,
            'need_up_down' => true,
            'allow_any_name' => false,
        ],
        'shemas' => [
            'description' => 'full database schemas',
            'behavior' => self::BEHAVIOR_SQL,
            'execute_always' => false,
            'need_up_down' => true,
            'allow_any_name' => false,
        ],
        'scripts' => [
            'description' => 'scripts',
            'behavior' => self::BEHAVIOR_SQL,
            'execute_always' => true,
            'need_up_down' => false,
            'allow_any_name' => true,
        ],
    ];
    protected $_migrationTypesDefault = ['functions', 'views', 'structures', 'datas', 'migrations', 'scripts'];
    private $_migrationBehaviors = [self::BEHAVIOR_SQL, self::BEHAVIOR_YII];

    public function init()
    {
        $this->_params = isset(Yii::app()->params['migraptor']) && is_array(Yii::app()->params['migraptor']) ? Yii::app()->params['migraptor'] : [];

        $migrationsBasePath = realpath(isset($this->_params['base_path']) ? $this->_params['base_path'] : Yii::app()->basePath . '/../migraptor');

        $this->_skipConnections = isset($this->_params['skip_connections']) ? $this->_params['skip_connections'] : [];
        if(!is_array($this->_skipConnections))
        {
            $this->_skipConnections = [$this->_skipConnections];
        }

        Yii::setPathOfAlias('migraptor', $migrationsBasePath);

        $this->_migrationsBasePath = 'migraptor';

        $this->_projectMigrationTypes = isset($this->_params['migration_types']) && is_array($this->_params['migration_types']) ? $this->_params['migration_types'] : [];

        $this->_migrationTypes = $this->_defaultMigrationTypes;

        $migrationTypesDefault = array_combine($this->_migrationTypesDefault, $this->_migrationTypesDefault);

        foreach ($this->_projectMigrationTypes as $migrationTypeKey => $migrationType) {
            if (isset($this->_migrationTypes[$migrationTypeKey])) {
                $projectMigrationType = $this->_projectMigrationTypes[$migrationTypeKey];
                if ($projectMigrationType === false) {
                    unset($this->_migrationTypes[$migrationTypeKey]);
                    unset($migrationTypesDefault[$migrationTypeKey]);
                    continue;
                }
            }

            $this->_migrationTypes[$migrationTypeKey] = $migrationType;
        }

        $this->_migrationTypesDefault = $migrationTypesDefault;

        $this->isDone = false;

        register_shutdown_function([$this, 'onShutdown']);
    }

    protected function beforeAction($action, $params)
    {
        CLIHelper::outputLn(CLIHelper::writeSuccessLn('Let\'s do this...'));

        return parent::beforeAction($action, $params);
    }

    protected function afterAction($action, $params, $exitCode = 0)
    {
        $this->isDone = true;

        return parent::afterAction($action, $params, $exitCode);
    }

    public function onShutdown()
    {
        if (!$this->isDone) {
            CLIHelper::output(CLIHelper::writeFatalLn(CLIHelper::EOL . 'Fatal error. Exit'));
        } else {
            CLIHelper::output(CLIHelper::writeSuccessLn(CLIHelper::EOL . 'Done'));
        }
    }

    protected function getConnections()
    {
        $connections = [];

        $components = Yii::app()->getComponents(false);

        /**
         * @desc Другого способа нет. Я узнавал. :)
         */
        foreach ($components as $componentName => $component) {
            $isDB = false;

            if(in_array($componentName, $this->_skipConnections))
            {
                continue;
            }

            if (is_object($component) && $component instanceof CDbConnection) {
                $connections[$componentName] = [
                    'connection_string' => $component->connectionString,
                    'username' => $component->username,
                    'password' => $component->password,
                ];
            } elseif (is_array($component) && isset($component['class']) && preg_match('/CDbConnection/', $component['class']) && isset($component['connectionString'])) {
                $connections[$componentName] = [
                    'connection_string' => $component['connectionString'],
                    'username' => $component['username'],
                    'password' => $component['password'],
                ];
            }
        }

        return $connections;
    }

    protected function checkConnectionsAndTypes(array $connection, array $type, $connections, $types)
    {
        if ($connections) {
            $connection[] = $connections;
        }

        $connections = [];

        foreach ($connection as $conn) {
            $ex = explode(',', $conn);
            $connections = array_merge($connections, $ex);
        }

        if ($types) {
            $type[] = $types;
        }

        $types = [];

        foreach ($type as $typ) {
            $ex = explode(',', $typ);
            $types = array_merge($types, $ex);
        }

        $projectConnections = array_keys($this->getConnections());

        if (!$connections) {
            $connections = $projectConnections;
        } else {
            $diff = array_diff($connections, $projectConnections);

            if ($diff) {
                $diffItem = reset($diff);
                throw new MigraptorValidationException('Unknown connection "' . $diffItem . '"');
            }

            $connections = array_intersect($connections, $projectConnections);
        }

        if (!$types) {
            $types = $this->_migrationTypesDefault;
        } else {
            $migrationTypes = array_keys($this->_migrationTypes);

            $diff = array_diff($types, $migrationTypes);

            if ($diff) {
                $diffItem = reset($diff);
                throw new MigraptorValidationException('Unknown type "' . $diffItem . '"');
            }

            $types = array_intersect($types, $migrationTypes);
        }

        return array($connections, $types);
    }

    protected function getMigrationVersion($connection, $migrationType)
    {
        if (!is_object($connection)) {
            $connection = Yii::app()->$connection;
        }

        /**
         * @var $db CDbConnection
         */
        $tableName = 'migraptor_migrations';
        /**
         * @TODO use Yii commands
         */
        $res = $connection->createCommand('SELECT EXISTS(SELECT 1 FROM pg_tables t WHERE t.tablename = :table)')->queryRow(true, [':table' => $tableName]);
        if($res && isset($res['exists']) && $res['exists'])
        {
            $res = $connection->createCommand('SELECT version_timestamp FROM migraptor_migrations WHERE migration_type = :type ORDER BY updated_at DESC LIMIT 1')->queryRow(true, [':type' => $migrationType]);
            if (!$res || !isset($res['version_timestamp']) || !$res['version_timestamp']) {
                $version = 0;
            } else {
                $version = strtotime($res['version_timestamp']);
                if (!$version) {
                    $version = 0;
                }
            }
        }else
        {
            $version = 0;
        }

        return $version;
    }

    protected function setMigrationVersion($connection, $migrationType, $version, $versionName)
    {
        if (!is_object($connection)) {
            $connection = Yii::app()->$connection;
        }

        $queryParams = [
            ':type' => $migrationType,
            ':versionts' => date('Y-m-d H:i:s', $version),
            ':version' => $versionName
        ];

        /**
         * @var $db CDbConnection
         */
        $tableName = 'migraptor_migrations';
        /**
         * @TODO use Yii commands
         */
        $res = $connection->createCommand('SELECT EXISTS(SELECT 1 FROM pg_tables t WHERE t.tablename = :table)')->queryRow(true, [':table' => $tableName]);
        if (!$res || !isset($res['exists']) || !$res['exists']) {
            $connection->createCommand('CREATE TABLE "' . $tableName . '" (migration_type varchar(255), version_timestamp timestamp, version varchar(255), updated_at timestamp)')->execute();
            $connection->createCommand('CREATE INDEX migraptor_migration_type_idx ON "' . $tableName . '"(migration_type)')->execute();
            $connection->createCommand('CREATE INDEX migraptor_version_timestamp_idx ON "' . $tableName . '"(version_timestamp)')->execute();
            $connection->createCommand('CREATE INDEX migraptor_version_idx ON "' . $tableName . '"(version)')->execute();
        }

        /**
         * @TODO use Yii commands
         */
        $res = $connection->createCommand('INSERT INTO migraptor_migrations (migration_type, version_timestamp, version, updated_at) VALUES (:type, :versionts, :version, NOW())')->execute($queryParams);

        return (bool)$res;
    }

    protected function removeMigrationVersion($connection, $migrationType, $version, $versionName)
    {
        if (!is_object($connection)) {
            $connection = Yii::app()->$connection;
        }

        $queryParams = [
            ':type' => $migrationType,
            ':versionts' => date('Y-m-d H:i:s', $version),
            ':version' => $versionName
        ];

        /**
         * @TODO use Yii commands
         */
        $res = $connection->createCommand('DELETE FROM migraptor_migrations WHERE migration_type = :type AND version_timestamp = :versionts AND version = :version')->execute($queryParams);

        return (bool)$res;
    }

    protected function checkConnectionAndMigration($connectionName, $migrationType)
    {
        $connections = $this->getConnections();

        $connectionInfo = isset($connections[$connectionName]) ? $connections[$connectionName] : false;
        if (!$connectionInfo) {
            throw new MigraptorFatalException('Connection "' . $connectionName . '" doesnt exists');
        }

        if (!isset(Yii::app()->$connectionName)) {
            throw new MigraptorFatalException('Connection "' . $connectionName . '" doesnt exists');
        }

        $migration = isset($this->_migrationTypes[$migrationType]) ? $this->_migrationTypes[$migrationType] : false;
        if (!$migration) {
            throw new MigraptorFatalException('Migration type "' . $migrationType . '" for connection "' . $connectionName . '" doesnt exists');
        }

        $behavior = isset($migration['behavior']) ? $migration['behavior'] : false;
        if (!$behavior || !in_array($behavior, $this->_migrationBehaviors)) {
            throw new MigraptorFatalException('Migration behavior "' . $behavior . '" for migration "' . $migrationType . '" doesnt exists');
        }

        $path = Yii::getPathOfAlias($this->_migrationsBasePath . '.' . $connectionName . '.' . $migrationType);
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $connection = Yii::app()->$connectionName;

        $isAlways = isset($migration['execute_always']) ? $migration['execute_always'] : false;

        if ($isAlways) {
            $version = 0;
        } else {
            $version = $this->getMigrationVersion($connection, $migrationType);
        }

        $needUpDown = isset($migration['need_up_down']) ? $migration['need_up_down'] : false;

        $allowAnyName = isset($migration['allow_any_name']) ? $migration['allow_any_name'] : false;

        return array($connectionInfo, $connection, $migration['execute_always'], $migration['behavior'], $version, $needUpDown, $allowAnyName);
    }

    protected function writeResultsTable($connectionName, $migrateType, $status)
    {
        CLIHelper::outputLn(sprintf("| %-25.25s | %-25.25s | %s", CLIHelper::writeInfo($connectionName), CLIHelper::writeInfo($migrateType), trim($status)));
    }

    protected function executeYiicMigrate($command, $key, $connectionId, $extraArg = null)
    {
        $arguments = [
            'yiic',
            'migrate',
            $command,
        ];

        if ($extraArg !== null) {
            $arguments[] = $extraArg;
        }

        $arguments[] = '--interactive=0';

        $arguments[] = '--migrationPath=' . $this->_migrationsBasePath . '.' . $connectionId . '.' . $key;

        $arguments[] = '--migrationTable=' . 'migraptor_' . 'yii' . '_' . $key; //  (version varchar(255) primary key, apply_time integer)

        $arguments[] = '--connectionID=' . $connectionId;

        //'--templateFile=',

        ob_start();
        $r = Yii::app()->commandRunner->run($arguments);
        $out = ob_get_clean();

        $out = str_replace("\nYii Migration Tool v1.0 (based on Yii v" . $yiiVersion = Yii::getVersion() . ")\n\n", '', $out);

        if (substr_count($out, "\n") > 1) {
            $out = CLIHelper::EOL . $out;
        }

        return [$r, $out];
    }

    protected function readMigrationFiles($path, $mask)
    {
        $res = [];

        $path = Yii::getPathOfAlias($path) . '/';
        $flags = FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;
        $pattern = '/\/' . $mask . '$/ui';

        $iterator = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, $flags)), $pattern, RecursiveRegexIterator::GET_MATCH);

        foreach ($iterator as $pathName => $fileInfo)
        {
            $res[$pathName] = new SplFileInfo($pathName);
        }

        uasort($res, function($a, $b) use ($pattern)
        {
            preg_match($pattern, '/'.$a->getBasename(), $m1);
            preg_match($pattern, '/'.$b->getBasename(), $m2);
            return (int)$m1[1] > (int)$m2[1];
        });

        return $res;
    }

    protected function filterMigrations(CDbConnection $connection, $migrateType, array $files, $fileMask, $filter, $limit = null)
    {
        $res = [];

        $version2file = [];
        foreach($files as $fileName => $file)
        {
            $fileBaseName = basename($fileName);
            if( preg_match('/'.$fileMask.'/i', $fileBaseName, $m) )
            {
                $version2file[$m[1].'_'.$m[2]] = $fileName;
            }
        }

        if($filter == self::FILTER_TYPE_GREATER)
        {
            $cRes = $connection->createCommand('SELECT EXISTS(SELECT 1 FROM pg_tables t WHERE t.tablename = :table)')->queryRow(true, [':table' => 'migraptor_migrations']);
            if($cRes && isset($cRes['exists']) && $cRes['exists'])
            {
                $oldVersions = $connection->createCommand('SELECT version FROM migraptor_migrations WHERE migration_type = :type ORDER BY updated_at ASC')->queryColumn([
                    'type' => $migrateType,
                ]);
            }else
            {
                $oldVersions = false;
            }

            if(!$oldVersions)
            {
                return $files;
            }
        }else
        {
            $oldVersions = [];
        }

        $versionsToApply = array_diff(array_keys($version2file), $oldVersions);

        if(!$versionsToApply)
        {
            return [];
        }

        if ((int)$limit <= 0) {
           $limit = self::MAX_LIMIT;
        }

        foreach($versionsToApply as $versionKey)
        {
            $fileName = $version2file[$versionKey];

            $res[$fileName] = $files[$fileName];
        }

        uasort($res, function($a, $b) use ($fileMask, $filter)
        {
            preg_match('/'.$fileMask.'/i', $a->getBaseName(), $m1);
            preg_match('/'.$fileMask.'/i', $b->getBaseName(), $m2);

            if($filter == self::FILTER_TYPE_GREATER)
            {
                return $m1[1] > $m2[1];
            }else
            {
                return $m2[1] > $m1[1];
            }
        });

        $res = array_slice($res, 0, $limit);

        return $res;
    }

    protected function migrateConnectionTest($connectionName, array $migrateTypes = [])
    {
        foreach ($migrateTypes as $migrateType) {
            try {
                $this->migrateConnectionTestType($connectionName, $migrateType);
            } catch (MigraptorException $e) {
                $this->applyException($e);
            }
        }
    }

    protected function migrateConnectionUp($connectionName, array $migrateTypes = [], $limit = null)
    {
        foreach ($migrateTypes as $migrateType) {
            try {
                $this->migrateConnectionUpType($connectionName, $migrateType, $limit);
            } catch (MigraptorException $e) {
                $this->applyException($e);
            }
        }
    }

    protected function migrateConnectionDown($connectionName, array $migrateTypes = [], $limit = null)
    {
        foreach ($migrateTypes as $migrateType) {
            try {
                $this->migrateConnectionDownType($connectionName, $migrateType, $limit);
            } catch (MigraptorException $e) {
                $this->applyException($e);
            }
        }
    }

    protected function migrateConnectionTo($connectionName, array $migrateTypes = [], $version = null)
    {
        foreach ($migrateTypes as $migrateType) {
            try {
                $this->migrateConnectionToType($connectionName, $migrateType, $version);
            } catch (MigraptorException $e) {
                $this->applyException($e);
            }
        }
    }

    protected function migrateConnectionMark($connectionName, array $migrateTypes = [], $version = null)
    {
        foreach ($migrateTypes as $migrateType) {
            try {
                $this->migrateConnectionMarkType($connectionName, $migrateType, $version);
            } catch (MigraptorException $e) {
                $this->applyException($e);
            }
        }
    }

    protected function migrateConnectionHistory($connectionName, array $migrateTypes = [], $limit = null)
    {
        foreach ($migrateTypes as $migrateType) {
            try {
                $this->migrateConnectionHistoryType($connectionName, $migrateType, $limit);
            } catch (MigraptorException $e) {
                $this->applyException($e);
            }
        }
    }

    protected function migrateConnectionNew($connectionName, array $migrateTypes = [], $limit = null)
    {
        foreach ($migrateTypes as $migrateType) {
            try {
                $this->migrateConnectionNewType($connectionName, $migrateType, $limit);
            } catch (MigraptorException $e) {
                $this->applyException($e);
            }
        }
    }

    protected function migrateConnectionCreate($connectionName, array $migrateTypes = [], $name = null)
    {
        foreach ($migrateTypes as $migrateType) {
            try {
                $this->migrateConnectionCreateType($connectionName, $migrateType, $name);
            } catch (MigraptorException $e) {
                $this->applyException($e);
            }
        }
    }

    protected function migrateConnectionExecute($connectionName, $migrateType, $action, $filterType = null, $limit = null)
    {
        list(, $connection, $executeAlways, $behavior, $version, $needUpDown, $allowAnyName) = $this->checkConnectionAndMigration($connectionName, $migrateType);

        $r = false;
        $out = null;

        switch ($behavior) {
            case self::BEHAVIOR_SQL:
                $fileMask = $allowAnyName ? '(\d+)_(\w+)\.sql' : ('m_(\d+)_(\w+)' . ($needUpDown ? '_' . $action : '') . '\.sql');

                $files = $this->readMigrationFiles($this->_migrationsBasePath . '.' . $connectionName . '.' . $migrateType, $fileMask);

                if(!$executeAlways)
                {
                    $files = $this->filterMigrations($connection, $migrateType, $files, $fileMask, $filterType, $limit);
                }

                $out = 'Total ' . sizeof($files) . ' new migration to be applied:' . CLIHelper::EOL;

                $isOk = true;

                foreach ($files as $fileName => $fileInfo) {
                    $tm = microtime(true);

                    $fName = basename($fileName);

                    $sql = file_get_contents($fileName);

                    try {
                        // Remove fuk'n BOM. Privet iz devyanostyh
                        if(
                            $sql &&
                            strlen($sql) >= 3 &&
                            ord($sql[0]) == 239 &&
                            ord($sql[1]) == 187 &&
                            ord($sql[2]) == 191
                        )
                        {
                            $sql = substr($sql, 3, strlen($sql));
                        }
                        //$sql = str_replace("\xEF\xBB\xBF",'',$sql); // Гармоничнее, но дольше работает на больших строках
                        /**
                         * @var PDO $pdo
                         */
                        if($sql)
                        {
                            $pdo = $connection->getPdoInstance();
                            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, 1);
                            $pdo->exec($sql);
                        }
                        $r = true;
                    } catch (PDOException $e) {
                        $r = $e;
                    }

                    $isOk = $isOk && ($r instanceof PDOException ? false : true);

                    $filePrefix = CLIHelper::TAB . '*** ' . $fName;
                    $timeStr = " (time: " . round(microtime(true) - $tm, 4) . "s)";

                    if ($r instanceof PDOException) {
                        $out .= CLIHelper::writeFailLn($filePrefix . ' is errored: ' . $e->getMessage() . $timeStr);
                    } else {
                        $out .= CLIHelper::writeSuccessLn($filePrefix . ' is ok ' . $timeStr);

                        if(!$executeAlways)
                        {
                            if( preg_match('/'.$fileMask.'/i', $fileName, $m) )
                            {
                                $version = $m[1].'_'.$m[2];

                                if($action == self::ACTION_UP)
                                {
                                    $this->setMigrationVersion($connectionName, $migrateType, $m[1], $version);
                                }else
                                {
                                    $this->removeMigrationVersion($connectionName, $migrateType, $m[1], $version);
                                }
                            }
                        }
                    }
                }

                if ($isOk) {
                    if ($files) {
                        $out .= CLIHelper::EOL . CLIHelper::TAB . CLIHelper::writeSuccessLn(':)');
                    } else {
                        $out = 'No new migration found. Your system is up-to-date.';
                    }
                } else {
                    $out .= CLIHelper::EOL . CLIHelper::TAB . CLIHelper::writeErrorLn(':(');
                }

                break;

            case self::BEHAVIOR_YII:
                list($r, $out) = $this->executeYiicMigrate($action, $migrateType, $connectionName, $limit);
                break;
        }

        $this->writeResultsTable($connectionName, $migrateType, $out);

        return $r;
    }

    protected function migrateConnectionTestType($connectionName, $migrateType)
    {
        static $lastConnection = false;

        $this->checkConnectionAndMigration($connectionName, $migrateType);

        if ($lastConnection === false || $lastConnection != $connectionName) {
            CLIHelper::outputLn($connectionName);
        }

        CLIHelper::outputLn(CLIHelper::TAB . '- ' . $migrateType);

        $lastConnection = $connectionName;
    }

    protected function migrateConnectionUpType($connectionName, $migrateType, $limit = null)
    {
        return $this->migrateConnectionExecute($connectionName, $migrateType, 'up', self::FILTER_TYPE_GREATER, $limit);
    }

    protected function migrateConnectionDownType($connectionName, $migrateType, $limit = null)
    {
        return $this->migrateConnectionExecute($connectionName, $migrateType, 'down', self::FILTER_TYPE_LOWER, $limit);
    }

    protected function migrateConnectionToType($connectionName, $migrateType, $version = null)
    {
        list(, , , $behavior, $currentVersion) = $this->checkConnectionAndMigration($connectionName, $migrateType);

        switch ($behavior) {
            case self::BEHAVIOR_SQL:
                if ($version < $currentVersion) {
                    return $this->migrateConnectionDownType($connectionName, $migrateType);
                } elseif ($version > $currentVersion) {
                    return $this->migrateConnectionUpType($connectionName, $migrateType);
                } else {
                    return false;
                }
                break;

            case self::BEHAVIOR_YII:
                return $this->migrateConnectionExecute($connectionName, $migrateType, 'to', null, $version);
                break;
        }
    }

    protected function migrateConnectionMarkType($connectionName, $migrateType, $version = null)
    {
        list(, , , $behavior, $currentVersion) = $this->checkConnectionAndMigration($connectionName, $migrateType);

        switch ($behavior) {
            case self::BEHAVIOR_SQL:
                /**
                 * @TODO
                 */
//                return $this->setMigrationVersion($connectionName, $migrateType, $version);
                break;

            case self::BEHAVIOR_YII:
                return $this->migrateConnectionExecute($connectionName, $migrateType, 'mark', null, $version);
                break;
        }
    }

    protected function migrateConnectionHistoryType($connectionName, $migrateType, $limit = null)
    {
        list(, $connection, , $behavior, $currentVersion, $needUpDown, $allowAnyName) = $this->checkConnectionAndMigration($connectionName, $migrateType);

        switch ($behavior) {
            case self::BEHAVIOR_SQL:
                if($currentVersion)
                {
                    $files = [];

                    $out = 'Showing the last '.sizeof($files).' applied migrations: '.CLIHelper::EOL;

                    /**
                     * @TODO
                     */
                }else
                {
                    $out = 'No migration has been done before.';
                }

                $this->writeResultsTable($connectionName, $migrateType, $out);

                break;

            case self::BEHAVIOR_YII:
                return $this->migrateConnectionExecute($connectionName, $migrateType, 'history', null, $limit);
                break;
        }
    }

    protected function migrateConnectionNewType($connectionName, $migrateType, $limit = null)
    {
        list(, $connection, $executeAlways, $behavior, $currentVersion, $needUpDown, $allowAnyName) = $this->checkConnectionAndMigration($connectionName, $migrateType);

        switch ($behavior) {
            case self::BEHAVIOR_SQL:
                $fileMask = $allowAnyName ? '(\w+)\.sql' : ('m_(\d+)_(\w+)' . ($needUpDown ? '_up' : '') . '\.sql');

                $files = $this->readMigrationFiles($this->_migrationsBasePath . '.' . $connectionName . '.' . $migrateType, $fileMask);

                if(!$executeAlways)
                {
                    $files = $this->filterMigrations($connection, $migrateType, $files, $fileMask, self::FILTER_TYPE_GREATER, $limit);
                }

                $out = 'Found '.sizeof($files).' new migration: '.CLIHelper::EOL;

                foreach ($files as $fileName => $fileInfo) {
                    $fName = basename($fileName);
                    $out .= '    ' . $fName . CLIHelper::EOL;
                }

                if (!$files) {
                    $out = 'No new migrations found. Your system is up-to-date.';
                }

                $this->writeResultsTable($connectionName, $migrateType, $out);

                break;

            case self::BEHAVIOR_YII:
                return $this->migrateConnectionExecute($connectionName, $migrateType, 'new', null, $limit);
                break;
        }
    }

    protected function migrateConnectionCreateType($connectionName, $migrateType, $name)
    {
        list(, , , $behavior, , $needUpDown, $allowAnyName) = $this->checkConnectionAndMigration($connectionName, $migrateType);

        switch ($behavior) {
            case self::BEHAVIOR_SQL:
                $path = Yii::getPathOfAlias($this->_migrationsBasePath . '.' . $connectionName . '.' . $migrateType) . '/';

                if ($needUpDown) {
                    file_put_contents($path . 'm_' . gmdate('U') . '_' . $name . '_up.sql', '');
                    file_put_contents($path . 'm_' . gmdate('U') . '_' . $name . '_down.sql', '');
                } elseif ($allowAnyName) {
                    file_put_contents($path . $name . '.sql', '');
                } else {
                    file_put_contents($path . 'm_' . gmdate('U') . '_' . $name . '.sql', '');
                }

                $this->writeResultsTable($connectionName, $migrateType, 'New migration created successfully.');
                break;

            case self::BEHAVIOR_YII:
                return $this->migrateConnectionExecute($connectionName, $migrateType, 'create', null, $name);
                break;
        }
    }

    protected function applyException(Exception $e)
    {
        CLIHelper::outputLn(CLIHelper::writeColoredLn($e->getMessage(), $e::COLOR));
        if ($e::IS_FATAL) {
            exit(1);
        }
    }

    protected function checkTypesAndConnectionsExists($type, $types, $connection, $connections)
    {
        if(!$type && !$types)
        {
            throw new MigraptorFatalException('You must provide a type(s) of migration to call this action');
        }

        if(!$connection && !$connections)
        {
            throw new MigraptorFatalException('You must provide a connection(s) of migration to call this action');
        }

        return true;
    }

    public function actionHelp($args = array())
    {
        $commandHelp = new CLIHelpHelper(__CLASS__);

        $baseCommandParams = [
            'connection' => [
                'description' => '',
                'can_be_array' => true,
                'is_required' => false,
            ],
            'connections' => [
                'description' => '',
                'is_required' => false,
            ],
            'type' => [
                'description' => '',
                'can_be_array' => true,
                'is_required' => false,
            ],
            'types' => [
                'description' => '',
                'is_required' => false,
            ],
        ];

        $commandHelp
            ->addTitle('-- Yii Migraptor Tool v0.1 (beta) --')

            ->addUsage('yiic migraptor [action] [param1] [paramN]')

            ->addDescription('Blah blah blah')

            ->addAction('help', '(or yiic migraptor --help) displays this message', [])

            ->addAction('list', 'list the available connections and migration types', [])

            ->addAction('test', 'test the migration', array_merge($baseCommandParams, []))

            ->addAction('up', 'up the migration', array_merge($baseCommandParams, []))

            ->addAction('down', 'down the migration', array_merge($baseCommandParams, []))

            ->addAction('to', 'to the migration', array_merge($baseCommandParams, []))

            ->addAction('mark', 'mark the migration', array_merge($baseCommandParams, []))

            ->addAction('history', 'to the migration', array_merge($baseCommandParams, []))

            ->addAction('new', 'show all new migrations', array_merge($baseCommandParams, []))

            ->addAction('create', 'create the migration', array_merge($baseCommandParams, []));

        CLIHelper::output($commandHelp);
    }

    public function actionIndex($help = null)
    {
        return $this->actionHelp();
    }

    public function actionList(array $args = [])
    {
        $what = reset($args);

        switch ($what) {
            default:
                CLIHelper::outputLn(CLIHelper::writeInfoLn('Usage: yiic migraptor list [list_type]'));

                $data = ['connections', 'types'];

                $what = 'lists';
                break;

            case 'connections':
                $data = array_keys($this->getConnections());
                break;

            case 'types':
                $data = [];
                foreach ($this->_migrationTypes as $migrationTypeKey => $migrationType) {
                    $migrationTypeDescription = isset($migrationType['description']) ? $migrationType['description'] : '';
                    $migrationTypeBehavior = isset($migrationType['behavior']) ? $migrationType['behavior'] : '';
                    $migrationTypeExecuteAlways = isset($migrationType['execute_always']) ? (bool)$migrationType['execute_always'] : false;
                    $migrationTypeIsDefault = isset($this->_migrationTypesDefault[$migrationTypeKey]);
                    $data[] = $migrationTypeKey . CLIHelper::EOL . CLIHelper::TAB . CLIHelper::TAB . ($migrationTypeIsDefault ? CLIHelper::writeColored('{default} ', CLIHelper::COLOR_LIGHT_GREEN) : '') . '[' . $migrationTypeBehavior . '] (' . ($migrationTypeExecuteAlways ? 'always' : 'not always') . ') ' . $migrationTypeDescription;
                }
                break;
        }

        CLIHelper::outputLn(CLIHelper::writeInfo('Available ' . $what . ':'));

        foreach ($data as $item) {
            CLIHelper::outputLn(CLIHelper::TAB . '- ' . $item);
        }
    }

    public function actionTest(array $connection = [], array $type = [], $connections = null, $types = null, array $args = [])
    {
        try {
            list($connections, $types) = $this->checkConnectionsAndTypes($connection, $type, $connections, $types);

            foreach ($connections as $connection) {
                $this->migrateConnectionTest($connection, $types);
            }
        } catch (MigraptorException $e) {
            $this->applyException($e);
        }
    }

    public function actionUp(array $connection = [], array $type = [], $connections = null, $types = null, array $args = [])
    {
        try {
            $limit = reset($args);

            list($connections, $types) = $this->checkConnectionsAndTypes($connection, $type, $connections, $types);

            foreach ($connections as $connection) {
                $this->migrateConnectionUp($connection, $types, $limit);
            }
        } catch (MigraptorException $e) {
            $this->applyException($e);
        }
    }

    public function actionDown(array $connection = [], array $type = [], $connections = null, $types = null, array $args = [])
    {
        try {
            $limit = reset($args);

            $this->checkTypesAndConnectionsExists($type, $types, $connection, $connections);

            list($connections, $types) = $this->checkConnectionsAndTypes($connection, $type, $connections, $types);

            foreach ($connections as $connection) {
                $this->migrateConnectionDown($connection, $types, $limit);
            }
        } catch (MigraptorException $e) {
            $this->applyException($e);
        }
    }

    public function actionTo(array $connection = [], array $type = [], $connections = null, $types = null, array $args = [])
    {
        try {
            $version = reset($args);

            $this->checkTypesAndConnectionsExists($type, $types, $connection, $connections);

            list($connections, $types) = $this->checkConnectionsAndTypes($connection, $type, $connections, $types);

            foreach ($connections as $connection) {
                $this->migrateConnectionTo($connection, $types, $version);
            }
        } catch (MigraptorException $e) {
            $this->applyException($e);
        }
    }

    public function actionMark(array $connection = [], array $type = [], $connections = null, $types = null, array $args = [])
    {
        try {
            $version = reset($args);

            if(!is_numeric($version) && !preg_match('/(\d+)\_(\d+)/i', $version))
            {
                throw new MigraptorFatalException('Version must be a valid timestamp id');
            }

            $this->checkTypesAndConnectionsExists($type, $types, $connection, $connections);

            list($connections, $types) = $this->checkConnectionsAndTypes($connection, $type, $connections, $types);

            foreach ($connections as $connection) {
                $this->migrateConnectionMark($connection, $types, $version);
            }
        } catch (MigraptorException $e) {
            $this->applyException($e);
        }
    }

    public function actionHistory(array $connection = [], array $type = [], $connections = null, $types = null, array $args = [])
    {
        try {
            $limit = reset($args);

            list($connections, $types) = $this->checkConnectionsAndTypes($connection, $type, $connections, $types);

            foreach ($connections as $connection) {
                $this->migrateConnectionHistory($connection, $types, $limit);
            }
        } catch (MigraptorException $e) {
            $this->applyException($e);
        }
    }

    public function actionNew(array $connection = [], array $type = [], $connections = null, $types = null, array $args = [])
    {
        try {
            $limit = reset($args);

            list($connections, $types) = $this->checkConnectionsAndTypes($connection, $type, $connections, $types);

            foreach ($connections as $connection) {
                $this->migrateConnectionNew($connection, $types, $limit);
            }
        } catch (MigraptorException $e) {
            $this->applyException($e);
        }
    }

    public function actionCreate(array $connection = [], array $type = [], $connections = null, $types = null, array $args = [])
    {
        try {
            $name = reset($args);

            if(!preg_match('/^(\w+)$/si', $name))
            {
                throw new MigraptorFatalException('Name of migration must regex \w+');
            }

            $this->checkTypesAndConnectionsExists($type, $types, $connection, $connections);

            list($connections, $types) = $this->checkConnectionsAndTypes($connection, $type, $connections, $types);

            foreach ($connections as $connection) {
                $this->migrateConnectionCreate($connection, $types, $name);
            }
        } catch (MigraptorException $e) {
            $this->applyException($e);
        }
    }
}