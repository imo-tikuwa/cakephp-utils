<?php
declare(strict_types=1);

namespace Utilities\Command\Helper;

use Cake\Console\Helper;

/**
 * Helper to display entity class data
 *
 * ## Usage
 *
 * The EntityHelper can be accessed from shells using the helper() method
 *
 * `↓SomethingCommand#execute()`
 * ```
 * public function execute(Arguments $args, ConsoleIo $io)
 * {
 *   $this->loadModel('Users');
 *
 *   $entity = $this->Users->find()->first();
 *   $io->helper('Utilities.Entity')->output($entity);
 *
 *   $entities = $this->Users->find()->toArray();
 *   $io->helper('Utilities.Entity')->output($entities);
 * }
 * ```
 */
class EntityHelper extends Helper
{
    /**
     * Default config for this helper.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'headers' => true,
        'rowSeparator' => false,
        'headerStyle' => 'info',
    ];

    /**
     * {@inheritDoc}
     */
    public function output($args): void
    {
        if (is_null($args)) {
            $this->_io->abort('$args is null');
        } elseif (!is_array($args)) {
            $entities = [$args];
        } else {
            $entities = $args;
        }

        // entity check.
        /** @var \Cake\ORM\Entity[] $entities */
        if (!($entities[0] instanceof \Cake\ORM\Entity)) {
            $this->_io->out('The data passed is not an entity class.');
            return;
        } else if (!class_exists(get_class($entities[0]))) {
            $this->_io->out('The detailed entity class was not found.');
            return;
        }

        $outputs = [];
        if ($this->getConfig('headers') === true) {
            $outputs[] = array_keys($entities[0]->getOriginalValues());
        }
        foreach ($entities as $entity) {
            $output = [];
            foreach ($entity->getOriginalValues() as $value) {
                if (is_null($value)) {
                    $output[] = '';
                } elseif (is_int($value) || is_float($value)) {
                    // int column case.
                    $output[] = (string) $value;
                } elseif (is_array($value)) {
                    // json column case.
                    $output[] = json_encode($value);
                } elseif ($value instanceof \Cake\I18n\FrozenDate || $value instanceof \Cake\I18n\FrozenTime) {
                    $output[] = $value->i18nFormat();
                } else {
                    $output[] = $value;
                }
            }
            $outputs[] = $output;
        }

        $this->_io->helper('Table', $this->getConfig())->output($outputs);
    }
}