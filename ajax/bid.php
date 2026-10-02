<?php
/**
 * ============================================================================
 * File: ajax/bid.php
 * Path: /ajax/bid.php
 * Description: Legacy AJAX Bid Gateway (Forwarded to Canonical place_bid.php)
 * Architecture: Senior PHP Full-Stack Standards
 * ============================================================================
 * 
 * Yeh file backward-compatibility maintain karti hai aur place_bid.php 
 * ke transaction-safe, anti-sniping engine ko call karti hai.
 */

require_once __DIR__ . '/../place_bid.php';
