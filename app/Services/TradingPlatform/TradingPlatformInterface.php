<?php

namespace App\Services\TradingPlatform;

interface TradingPlatformInterface
{
    /**
     * Fetch user info by meta login.
     */
    public function getUser($metaLogin): array;

    /**
     * Fetch account info by meta login.
     */
    public function getAccount($metaLogin): array;

    /**
     * Create deal for meta login.
     */
    public function createDeal($meta_login, $amount, $comment, $type, $expire_date): array;
}
