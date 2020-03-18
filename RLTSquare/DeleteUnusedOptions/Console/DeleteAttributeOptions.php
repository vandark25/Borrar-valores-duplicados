<?php

namespace RLTSquare\DeleteUnusedOptions\Console;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class DeleteAttributeOptions
 * @package RLTSquare\DeleteUnusedOptions\Console
 */
class DeleteAttributeOptions extends Command
{
    /**
     * @var State
     */
    private $state;
    /**
     * @var Registry
     */
    private $registry;
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;
    /**
     * @var Attribute
     */
    private $eavAttribute;

    /**
     * DeleteAttributeOptions constructor.
     * @param State $state
     * @param ResourceConnection $resourceConnection
     * @param Attribute $eavAttribute
     * @param Registry $registry
     * @param string|null $name
     */
    public function __construct(
        State $state,
        ResourceConnection $resourceConnection,
        Attribute $eavAttribute,
        Registry $registry,
        ?string $name = null
    ) {
        $this->state = $state;
        parent::__construct($name);
        $this->registry = $registry;
        $this->resourceConnection = $resourceConnection;
        $this->eavAttribute = $eavAttribute;
    }

    protected function configure()
    {
        $this->setName('rlt:delete-attribute-options');
        $this->setDescription('Delete Unused attribute options.');

        $this->addOption(
            'attributeCode',
            null,
            InputOption::VALUE_REQUIRED,
            'Attribute code of attribute whose options you want to delete.'
        );

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('attributeCode')) {
            $output->writeln('Please specify attribute code in the command using --atributeCode argument.');
            return;
        }
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
            $this->registry->register('isSecureArea', true);
        } catch (\Magento\Framework\Exception\LocalizedException $exception) {
        }
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            'Are you sure to delete attribute options? [y/N]',
            false
        );
        if ($helper->ask($input, $output, $question)) {
            $finalConfirmation = new ConfirmationQuestion(
                'This will delete unused attribute option for the attribute you specified. Are you 100% sure about it? [y/N]',
                false
            );
            if ($helper->ask($input, $output, $finalConfirmation)) {
                $this->deleteEntities($input, $output);
            } else {
                $output->writeln('Nothing deleted.');
            }
        } else {
            $output->writeln('Nothing deleted.');
        }
    }

    /**
     * @param string $code
     * @param OutputInterface $output
     * @return int
     */
    private function deleteAttributeOptions($code, $output)
    {
        $connection = $this->resourceConnection->getConnection();
        $attrId = $this->eavAttribute->getIdByCode(ProductAttributeInterface::ENTITY_TYPE_CODE, $code);

        //fetching all options related to specified attribute
        $eavTableName = $connection->getTableName('eav_attribute_option');
        $selectOne = $connection->select()->from(
            ['eavAtt' => $eavTableName],
            ['option_id']
        )->where('attribute_id IN(?)', $attrId);
        $options = $selectOne->query()->fetchAll();
        $options = array_column($options, 'option_id');

        //fetching options that are assigned to product for that attribute
        $prodTableName = $connection->getTableName('catalog_product_entity_int');
        $selectTwo = $connection->select()->from(
            ['catPrd' => $prodTableName],
            ['value']
        )->where('attribute_id IN(?)', $attrId);
        $usedOptions = $selectTwo->query()->fetchAll();
        $usedOptions = array_column($usedOptions, 'value');

        //difference of both to get unused option ids which will be used for delete operation
        $notUsedOptions = array_diff($options, $usedOptions);
        $whereForDelete = ['option_id IN(?)' => $notUsedOptions];

        $output->writeln(
            'Found ' . count($notUsedOptions) . ' unused options for attribute code => ' . $code
        );
        return $connection->delete($eavTableName, $whereForDelete);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function deleteEntities(InputInterface $input, OutputInterface $output): void
    {
        $attrCode = $input->getOption('attributeCode');

        $output->writeln('Deleting Unused Attribute options.');
        $this->deleteAttributeOptions($attrCode, $output);
        $output->writeln('Deleted unused attribute options.');
    }
}
