<?php

use LeoCarmo\CircuitBreaker\CircuitBreaker;

class SettingsTest extends \PHPUnit\Framework\TestCase
{

    public function testSetGlobalSettings()
    {
        $settings = [
            'timeWindow' => 20,
            'failureRateThreshold' => 5,
            'intervalToHalfOpen' => 10,
        ];

        CircuitBreaker::setGlobalSettings($settings);

        $globalSettings = CircuitBreaker::getGlobalSettings();

        $this->assertEquals($settings, $globalSettings);
    }

    public function testGetServiceSettingFromGlobalSettings()
    {
        $serviceSetting = CircuitBreaker::getServiceSetting('my-service', 'failureRateThreshold');

        $this->assertEquals(5, $serviceSetting);
    }

    public function testSetServiceSettings()
    {
        $failureRateThreshold = 20;

        CircuitBreaker::setServiceSettings('my-service-2', [
            'failureRateThreshold' => $failureRateThreshold,
        ]);

        $serviceSetting = CircuitBreaker::getServiceSetting('my-service-2', 'failureRateThreshold');

        $this->assertEquals($failureRateThreshold, $serviceSetting);
    }
}
