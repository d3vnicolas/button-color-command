<?php
/**
 * Copyright © devnicolas. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Devnicolas\ButtonColor\Console\Command;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Console\Cli;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to change button color for a specific store view
 */
class ChangeButtonColor extends Command
{
    /**
     * Configuration path for button color
     */
    private const CONFIG_PATH = 'devnicolas/button_color/color';

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var CacheManager
     */
    private $cacheManager;

    /**
     * @param WriterInterface $configWriter
     * @param StoreRepositoryInterface $storeRepository
     * @param CacheManager $cacheManager
     * @param string|null $name
     */
    public function __construct(
        WriterInterface $configWriter,
        StoreRepositoryInterface $storeRepository,
        CacheManager $cacheManager,
        ?string $name = null
    ) {
        $this->configWriter = $configWriter;
        $this->storeRepository = $storeRepository;
        $this->cacheManager = $cacheManager;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('color:change')
            ->setDescription('Change button color for a specific store view or reset to remove custom colors')
            ->setDefinition([
                new InputArgument(
                    'hexColor',
                    InputArgument::OPTIONAL,
                    'Hex color code (with or without #, e.g., 000000 or #000000). Required unless --reset is used.'
                ),
                new InputArgument(
                    'storeViewId',
                    InputArgument::OPTIONAL,
                    'Store view ID. Required unless --reset is used.'
                ),
                new InputOption(
                    'reset',
                    'r',
                    InputOption::VALUE_NONE,
                    'Reset button color configuration (removes inline styles)'
                )
            ]);

        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reset = $input->getOption('reset');

        // Handle reset option
        if ($reset) {
            return $this->handleReset($input, $output);
        }

        // Handle normal color change
        return $this->handleColorChange($input, $output);
    }

    /**
     * Handle reset option - remove button color configuration
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function handleReset(InputInterface $input, OutputInterface $output): int
    {
        // When --reset is used, the first argument should be storeViewId
        // Try to get storeViewId from the second argument first, if not available, try the first
        $storeViewId = $input->getArgument('storeViewId');
        $hexColor = $input->getArgument('hexColor');

        // If storeViewId is not provided but hexColor is, use hexColor as storeViewId
        // (this handles the case where user passes: --reset 1)
        if (($storeViewId === null || $storeViewId === '') && $hexColor !== null && $hexColor !== '' && is_numeric($hexColor)) {
            $storeViewId = $hexColor;
        }

        // Validate store view ID is provided
        if ($storeViewId === null || $storeViewId === '') {
            $output->writeln('<error>Store view ID is required when using --reset option.</error>');
            return Cli::RETURN_FAILURE;
        }

        // Validate store view ID
        if (!is_numeric($storeViewId)) {
            $output->writeln('<error>Store view ID must be a numeric value.</error>');
            return Cli::RETURN_FAILURE;
        }

        $storeViewId = (int)$storeViewId;

        // Validate store view exists
        try {
            $store = $this->storeRepository->getById($storeViewId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $output->writeln(
                '<error>Store view with ID ' . $storeViewId . ' does not exist.</error>'
            );
            return Cli::RETURN_FAILURE;
        }

        // Delete configuration
        try {
            $this->configWriter->delete(
                self::CONFIG_PATH,
                ScopeInterface::SCOPE_STORES,
                $storeViewId
            );

            // Clean config cache
            $this->cacheManager->clean([\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER]);

            $output->writeln(
                '<info>Button color configuration successfully reset for store view "' .
                $store->getName() . '" (ID: ' . $storeViewId . ').</info>'
            );
            $output->writeln('<info>Inline styles have been removed. Configuration cache has been cleared.</info>');

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Handle normal color change
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function handleColorChange(InputInterface $input, OutputInterface $output): int
    {
        $hexColor = $input->getArgument('hexColor');
        $storeViewId = $input->getArgument('storeViewId');

        // Validate arguments are provided
        // Use strict comparison to handle 0 as valid storeViewId
        if ($hexColor === null || $hexColor === '' || $storeViewId === null || $storeViewId === '') {
            $output->writeln('<error>Both hexColor and storeViewId are required when not using --reset option.</error>');
            return Cli::RETURN_FAILURE;
        }

        // Validate hex color format
        $hexColor = $this->normalizeHexColor($hexColor);
        if (!$this->isValidHexColor($hexColor)) {
            $output->writeln(
                '<error>Invalid hex color format. Please provide a valid 6-character hexadecimal color (e.g., 000000 or #000000).</error>'
            );
            return Cli::RETURN_FAILURE;
        }

        // Validate store view ID
        if (!is_numeric($storeViewId)) {
            $output->writeln('<error>Store view ID must be a numeric value.</error>');
            return Cli::RETURN_FAILURE;
        }

        $storeViewId = (int)$storeViewId;

        // Validate store view exists
        try {
            $store = $this->storeRepository->getById($storeViewId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $output->writeln(
                '<error>Store view with ID ' . $storeViewId . ' does not exist.</error>'
            );
            return Cli::RETURN_FAILURE;
        }

        // Save configuration
        try {
            $this->configWriter->save(
                self::CONFIG_PATH,
                $hexColor,
                ScopeInterface::SCOPE_STORES,
                $storeViewId
            );

            // Clean config cache
            $this->cacheManager->clean([\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER]);

            $output->writeln(
                '<info>Button color successfully changed to #' . $hexColor . ' for store view "' .
                $store->getName() . '" (ID: ' . $storeViewId . ').</info>'
            );
            $output->writeln('<info>Configuration cache has been cleared.</info>');

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Normalize hex color (remove # if present, convert to uppercase)
     *
     * @param string $hexColor
     * @return string
     */
    private function normalizeHexColor(string $hexColor): string
    {
        $hexColor = trim($hexColor);
        $hexColor = ltrim($hexColor, '#');
        return strtoupper($hexColor);
    }

    /**
     * Validate hex color format
     *
     * @param string $hexColor
     * @return bool
     */
    private function isValidHexColor(string $hexColor): bool
    {
        // Aceita 3 ou 6 caracteres hexadecimais (já sem #, pois normalizeHexColor remove)
        return preg_match('/^([0-9A-F]{3}|[0-9A-F]{6})$/i', $hexColor) === 1;
    }
}

