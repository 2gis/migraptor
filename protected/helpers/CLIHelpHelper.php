<?php

/**
 * Class CLIHelpHelper
 *
 * @TODO From reflection, read all methods matched /^action\w+/ and read PHPDoc
 * @example
 * $commandHelp = new CLIHelpHelper('YourClassName');
 * $commandHelp
 * ->addTitle('-- Some Yii Tool v1.0 --')
 *
 * ->addUsage('yiic command [action] [parameters]')
 *
 * ->addDescription('Blah blah blah')
 *
 * ->addAction('help', 'displays this message', [])
 *
 * ->addAction('test', 'simple test command', [
 * 'foo' => [
 *      'description' => 'The foo param',
 *      'is_required' => false,
 *      'value_description' => 'YOUR-FOO-VALUE',
 *      'default_value' => 'false',
 * ],
 * 'bar' => [
 *      'description' => 'The bar param',
 *      'is_required' => false,
 * ],
 * ]);
 */
class CLIHelpHelper
{
    protected $className;
    protected $titles = [];
    protected $usages = [];
    protected $descriptions = [];
    protected $actions = [];
    protected $examples = [];
    protected $infoBlocks = [];

    public function __construct($className)
    {
        $this->className = $className;
    }

    /**
     * @param string $title
     * @return CLIHelpHelper $this
     */
    public function addTitle($title)
    {
        $this->titles[] = $title;

        return $this;
    }

    /**
     * @param string $usage
     * @return CLIHelpHelper $this
     */
    public function addUsage($usage)
    {
        $this->usages[] = $usage;

        return $this;
    }

    /**
     * @param string $description
     * @return CLIHelpHelper $this
     */
    public function addDescription($description)
    {
        $this->descriptions[] = $description;

        return $this;
    }

    /**
     * @param string $name
     * @param string $description
     * @param array $params
     * @return CLIHelpHelper $this
     */
    public function addAction($name, $description = null, array $params = [])
    {
        $this->actions[$name] = [
            'name' => $name,
            'description' => $description,
            'params' => $params,
        ];

        return $this;
    }

    /**
     * @param string $example
     * @return CLIHelpHelper $this
     */
    public function addExample($example)
    {
        $this->examples[] = $example;

        return $this;
    }

    /**
     * @param string $title
     * @param array $content
     * @return CLIHelpHelper $this
     */
    public function addInfoBlock($title, array $content)
    {
        $this->infoBlocks[$title] = $content;

        return $this;
    }

    /**
     * @return array
     */
    protected function buildActions()
    {
        $res = [];

        foreach ($this->actions as $commandName => $command) {
            $description = isset($command['description']) ? $command['description'] : '';
            $params = isset($command['params']) && is_array($command['params']) ? $command['params'] : [];

            $buf = '';

            $buf .= CLIHelper::writeColoredLn($commandName, CLIHelper::COLOR_CYAN);
            if ($description) {
                $buf .= CLIHelper::TAB . CLIHelper::TAB . $description . CLIHelper::EOL;
            }
            $buf .= CLIHelper::TAB . CLIHelper::TAB . 'Usage:' . CLIHelper::EOL;

            foreach ($params as $paramName => $param) {
                $paramValueDescription = isset($param['value_description']) ? $param['value_description'] : '';
                $paramDescription = isset($param['description']) ? $param['description'] : '';
                $paramDefaultValue = isset($param['default_value']) ? $param['default_value'] : '';

                $buf .= CLIHelper::TAB . CLIHelper::TAB . CLIHelper::TAB . '--' . $paramName . ($paramValueDescription ? '=' . $paramValueDescription : '') . CLIHelper::TAB . CLIHelper::TAB . $paramDescription . CLIHelper::EOL;
                if ($paramDefaultValue) {

                }
            }

            $buf .= CLIHelper::EOL;

            $res[] = $buf;
        }

        return $res;
    }

    /**
     * @return string
     */
    public function generate()
    {
        $res = '';

        foreach($this->titles as $title)
        {
            $res .= CLIHelper::EOL . CLIHelper::writeColoredLn($title, CLIHelper::COLOR_LIGHT_PURPLE) . CLIHelper::EOL;
        }

        if($this->usages)
        {
            $this->addInfoBlock('USAGE', $this->usages);
        }

        if($this->descriptions)
        {
            $this->addInfoBlock('DESCRIPTION', $this->descriptions);
        }

        if($this->actions)
        {
            $this->addInfoBlock('ACTIONS', $this->buildActions());
        }

        if($this->examples)
        {
            $this->addInfoBlock('EXAMPLES', $this->examples);
        }

        foreach($this->infoBlocks as $infoBlockTitle => $infoBlockContents)
        {
            $res .= CLIHelper::writeColoredLn($infoBlockTitle, CLIHelper::COLOR_PURPLE);

            $res .= implode(CLIHelper::EOL, array_map(function($v)
            {
                return CLIHelper::TAB.$v;
            }, $infoBlockContents));

            $res .= CLIHelper::EOL.CLIHelper::EOL;
        }

        return $res;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->generate();
    }
}