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
        // When true, the hidden column is output.
        'hiddenField' => false,
        // The following 3 properties are \Cake\Shell\Helper\TableHelper
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
        } elseif (!isset($args['target'])) {
            $this->_io->abort('$args[\'target\'] is required');
        } elseif (!is_array($args['target'])) {
            $entities = [$args['target']];
        } else {
            $entities = $args['target'];
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

        // exclude hidden fields.
        $hidden_fields = $entities[0]->getHidden();
        $output_fields = array_keys($entities[0]->getOriginalValues());
        if (!empty($hidden_fields) && !$this->getConfig('hiddenField')) {
            $output_fields = array_diff($output_fields, $entities[0]->getHidden());
        }

        $outputs = [];
        if ($this->getConfig('headers') === true) {
            $outputs[] = $output_fields;
        }
        foreach ($entities as $entity) {
            $output = [];
            foreach ($entity->getOriginalValues() as $field => $value) {
                if (!in_array($field, $output_fields, true)) {
                    continue;
                } elseif (is_null($value)) {
                    $output[] = '';
                } elseif (is_int($value) || is_float($value) || is_bool($value)) {
                    $output[] = (string) $value;
                } elseif (is_array($value)) {
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