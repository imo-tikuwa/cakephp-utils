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
 *   $this->loadModel('Articles');
 *
 *   $entity = $this->Articles->find()->first();
 *   $io->helper('Utilities.Entity')->output($entity);
 *
 *   $entities = $this->Articles->find()->toArray();
 *   $io->helper('Utilities.Entity')->output($entities);
 * }
 * ```
 *
 * If you don't want to see some fields, you can control the display by doing setHidden on the first entity.
 * (Properties set in the entity with $ _hidden are hidden by default.)
 * ```
 *   $entity = $this->Articles->find()->first();
 *   $entity->setHidden(['slug', 'created']);
 *   $io->helper('Utilities.Entity')->output($entity);
 *
 *   $entities = $this->Articles->find()->toArray();
 *   $entities[0]->setHidden(['slug', 'created']);
 *   $io->helper('Utilities.Entity')->output($entities);
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
        // Upper limit of the number of display characters per column
        // (character strings that exceed the specified number are replaced with ...).
        // Show everything when null is set.
        // This setting is valid for varchar, text and json items.
        'eachStrLength' => 30,
        // Upper limit of the number of characters that can be displayed at the relation destination.
        // (The relation destination data is converted to a character string with json_encode and displayed.)
        // Show everything when null is set.
        'relationStrLength' => 50,
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
        if (!isset($args['target'])) {
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
            $output_fields = array_diff($output_fields, $hidden_fields);
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
                } elseif ($value instanceof \Cake\ORM\Entity || (is_array($value) && isset($value[0]) && $value[0] instanceof \Cake\ORM\Entity)) {
                    $output[] = $this->_substr(json_encode($value, JSON_UNESCAPED_UNICODE), $this->getConfig('relationStrLength'));
                } elseif (is_int($value) || is_float($value) || is_bool($value)) {
                    $output[] = (string) $value;
                } elseif (is_array($value)) {
                    $output[] = $this->_substr(json_encode($value, JSON_UNESCAPED_UNICODE), $this->getConfig('eachStrLength'));
                } elseif ($value instanceof \Cake\I18n\FrozenDate || $value instanceof \Cake\I18n\FrozenTime) {
                    $output[] = $value->i18nFormat();
                } else {
                    $output[] = $this->_substr(str_replace(["\r", "\n"], '', $value), $this->getConfig('eachStrLength'));
                }
            }
            $outputs[] = $output;
        }

        $this->_io->helper('Table', $this->getConfig())->output($outputs);
    }

    /**
     * Extract the character string by referring to the setting value of eachStrLength
     *
     * @param string $str substring target.
     * @param int|null $length substring length.
     * @return string
     */
    protected function _substr($str, $length = null)
    {
        if (!is_null($length) && $length != '' && mb_strlen($str) > (int) $length) {
            return mb_substr($str, 0, $length) . '...';
        }
        return $str;
    }
}