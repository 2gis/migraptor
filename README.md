#Migraptor. Добро пожаловать#

##Общие сведения##
Migraptor - утилита для управления миграциями данных, структур, схем, процедур, представлений и т.п. для баз данных проектов, реализованных на Yii 1. Реализован в виде консольной команды для yiic.

##Подключение к проекту##
Для подключения к своему проекту, добавьте следующую строку в конфиг:

    Конфиг проекта
    'commandMap' => array( // Блок commandMap конфига
    //...
        'migraptor' => Yii::getPathOfAlias('dg') . '/commands/' . 'MigraptorCommand.php', // Команда включена в ядро dg. Исправьте путь до команды по необходимости
    //...
    ),

##Использование##
Для запуска команды, наберите в терминале:

    /path/to/yiic migraptor

##Конфигурирование##
Для изменения параметров команды по-умолчанию, добавьте в конфиг проекта следующие строки:

    Конфиг проекта
    'params' => array( // Блок params конфига
    //...
        'migraptor' => array(
            'base_path' => realpath(Yii::app()->basePath . '/../migraptor'), // Путь до папки с миграциями. По желанию можно изменить
            'migration_types' => [ // Типы миграций
     
            ],
        ),
    //...
    ),

##Множественные коннекторы##
Migraptor поддерживает множество коннекторов (все компоненты, которые вы унаследуете от CDbConnection)
Список доступных подключений можно узнать, введя команду:

    /path/to/yiic migraptor list connections

##Типы (сценарии) миграций##
Можно получить подробную информацию, введя команду:

    /path/to/yiic migraptor list types

##Типы по-умолчанию##
Тип
Название
Описание
Поведение
Запускается всегда?
По-умолчанию?
functions    Функции	Хранимые функции и процедуры	sql	(tick)	(tick)
views	Представления	Представления	sql	(tick)	(tick)
structures	Структуры (DDL)	Изменения структур	sql	 	(tick)
datas	Данные (DML)	Изменения данных	sql	 	(tick)
migrations	Миграции	Yii миграции	yii	 	(tick)
schemas	Схемы	Полная схема базы / таблицы итп	sql	 	 
scripts	Скрипты	Скрипты	sql	(tick)	(tick)

##Пример описания нового типа##
    
    Конфиг проекта
    //...
        'migraptor' => array(
            //...
            'migration_types' => [
                //...
                'some_type' => [
                    'description' => 'your some type description',
                    'behavior' => MigraptorCommand::BEHAVIOR_SQL,
                    'execute_always' => false,
                    'need_up_down' => true,
                    'allow_any_name' => false,
                ],
                //...
            ],
            //...
        ),
    //...

##Отключение миграций, заданных по-умолчанию##
Для отключения миграции, заданной по-умолчанию, необходимо добавить в конфиг следующие строки:
 
    Конфиг проекта
    //...
        'migraptor' => array(
            //...
            'migration_types' => [
                //...
                'default_type' => false,
                //...
            ],
            //...
        ),
    //...
    Пример:
    Конфиг проекта
    //...
        'migraptor' => array(
            //...
            'migration_types' => [
                //...
                'functions' => false,
                //...
            ],
            //...
        ),
    //...
 
##Базовые параметры команд и примеры использования##

Команды
###help
Basic usage:

    /path/to/yiic migraptor [help] [--help]
Выводит справку

###list
Basic usage:

    /path/to/yiic migraptor list [connections|types]
Выводит доступные подключения или типы миграций

###test
Basic usage:

    /path/to/yiic migraptor test
Выводит древовидный список того, что будет выполнено в процессе up

###up
Basic usage:

    /path/to/yiic migraptor up [limit]
Актуализирует миграцию до последней версии, начиная с текущей, ограничиваясь количеством через параметр limit (если не задан - актуализирует всё)

###down
Basic usage:
    
    /path/to/yiic migraptor down [limit]
Деградирует миграцию до первой версии, начиная с текущей, ограничиваясь количеством через параметр limit (если не задан - деградирует всё)

###to
Basic usage:

    /path/to/yiic migraptor to version
Актуализирует или деградирует миграцию до версии version

###mark
Basic usage:

    /path/to/yiic migraptor mark version
    
Установит текущую версию как version. Миграции выполнены не будут

###history
Basic usage:

    /path/to/yiic migraptor history [limit]

История миграций. Выводит limit  последних миграций

###new
Basic usage:
    
    /path/to/yiic migraptor new [limit]

Выводит новые миграции, которые еще не выполнены, но будут выполнены через команду up, ограничиваясь количеством через параметр limit

###create
Basic usage:

    /path/to/yiic migraptor create name

Создаёт новую миграцию с именем name

##Примеры##
###Посмотрим, что было (последние 10 миграций)
    /path/to/yiic migraptor history 10
###Посмотрим, что есть нового
    /path/to/yiic migraptor new
###Актуализируем весь проект
    /path/to/yiic migraptor up
###Актуализируем только хранимые процедуры
    /path/to/yiic migraptor up --type=functions
###Актуализируем только хранимые процедуры и представления
    /path/to/yiic migraptor up --types=functions,views
###Актуализируем только хранимые процедуры для подключения db2
    /path/to/yiic migraptor up --type=functions --connection=db2
###Актуализируем только хранимые процедуры и представления для подключения db2 и db3
    /path/to/yiic migraptor up --type=functions --type=views --connections=db2,db3
