<?php
/**
 * Copyright © devnicolas. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Devnicolas\ButtonColor\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Block to inject custom button color CSS
 */
class ButtonColor extends Template
{
    /**
     * Configuration path for button color
     */
    private const CONFIG_PATH = 'devnicolas/button_color/color';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get button color for current store view
     *
     * @return string|null
     */
    public function getButtonColor(): ?string
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $color = $this->scopeConfig->getValue(
            self::CONFIG_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $color ?: null;
    }

    /**
     * Check if button color is configured
     *
     * @return bool
     */
    public function hasButtonColor(): bool
    {
        return $this->getButtonColor() !== null;
    }

    /**
     * Get CSS for button color
     *
     * @return string
     */
    public function getButtonColorCss(): string
    {
        $color = $this->getButtonColor();
        if (!$color) {
            return '';
        }

        // Ensure color has # prefix
        $hexColor = '#' . ltrim($color, '#');

        // Generate CSS that targets all buttons
        $css = <<<CSS
button,
.action.primary,
.btn-primary,
[class*="button"],
button.action,
button.action-primary,
.action-primary,
button.primary,
.primary,
button.secondary,
.secondary,
button.action.secondary,
.action.secondary {
    background-color: {$hexColor} !important;
    border-color: {$hexColor} !important;
}

button:hover,
.action.primary:hover,
.btn-primary:hover,
[class*="button"]:hover,
button.action:hover,
button.action-primary:hover,
.action-primary:hover,
button.primary:hover,
.primary:hover {
    background-color: {$hexColor} !important;
    border-color: {$hexColor} !important;
    opacity: 0.9;
}
CSS;

        return $css;
    }
}
