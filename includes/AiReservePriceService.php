<?php
/**
 * AI Dynamic Reserve Price Service
 * File: includes/AiReservePriceService.php
 * 
 * Analyzes multi-dimensional auction metrics:
 * - Starting Price
 * - Previous Bids & Existing Bid Velocity
 * - Current Market Price
 * - Category Trends & Liquidity
 * - Brand Desirability Index
 * - Model & Series Demand
 * - Manufacturing Year & Depreciation / Antique Appreciation
 * - Physical Condition Rating
 * - Geographic Location & Regional Buying Power
 * - Previous Similar Auctions (Comps Database)
 * - Watchers & Live Demand Velocity
 */

require_once __DIR__ . '/database.php';

class AiReservePriceService
{
    private PDO $pdo;

    // Premium Brand Desirability Weights
    private array $premiumBrands = [
        'apple' => 1.12, 'rolex' => 1.18, 'toyota' => 1.09, 'honda' => 1.07,
        'mercedes' => 1.12, 'mercedes-benz' => 1.12, 'bmw' => 1.11, 'audi' => 1.10,
        'sony' => 1.06, 'samsung' => 1.06, 'patek philippe' => 1.25, 'cartier' => 1.15,
        'omega' => 1.14, 'porsche' => 1.18, 'dell' => 1.04, 'hp' => 1.03,
        'canon' => 1.06, 'nikon' => 1.06, 'dha' => 1.12, 'bahria town' => 1.08
    ];

    // Condition Multipliers
    private array $conditionMultipliers = [
        'brand new'                => 1.08,
        'box packed'               => 1.08,
        'used - like new'          => 1.03,
        'like new'                 => 1.03,
        'used - excellent'         => 0.98,
        'used - good'              => 0.90,
        'good'                     => 0.90,
        'used - fair'              => 0.76,
        'fair'                     => 0.76,
        'refurbished'              => 0.82,
        'antique / vintage'        => 1.22,
        'vintage'                  => 1.18,
        'for parts / not working'  => 0.42,
    ];

    // Tier 1 and Tier 2 Cities in Pakistan
    private array $metroCities = [
        'karachi'    => 1.05,
        'lahore'     => 1.05,
        'islamabad'  => 1.06,
        'rawalpindi' => 1.04,
        'faisalabad' => 1.02,
        'multan'     => 1.02,
        'peshawar'   => 1.02,
        'sialkot'    => 1.02,
        'gujranwala' => 1.02,
        'quetta'     => 1.01
    ];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * Calculate dynamic reserve price recommendations and valuation analysis
     * 
     * @param array $params
     * @return array
     */
    public function calculate(array $params): array
    {
        $startingPrice = (float)($params['starting_price'] ?? 0);
        $categoryId    = (int)($params['category_id'] ?? 0);
        $brand         = trim($params['brand'] ?? '');
        $model         = trim($params['model'] ?? '');
        $year          = !empty($params['year']) ? (int)$params['year'] : null;
        $condition     = trim($params['condition_type'] ?? '');
        $city          = trim($params['city'] ?? '');
        $itemId        = !empty($params['item_id']) ? (int)$params['item_id'] : null;

        if ($startingPrice <= 0) {
            return [
                'success' => false,
                'message' => 'Starting price must be greater than zero.'
            ];
        }

        // 1. Fetch Similar Auctions from DB
        $compsData = $this->fetchHistoricalComps($categoryId, $brand, $model, $itemId);

        // 2. Fetch Existing Item Real-time Bid Data (if existing item)
        $existingItemData = $this->fetchExistingItemData($itemId);

        // 3. Category & Market baseline
        $categoryInfo = $this->fetchCategoryInfo($categoryId);

        // 4. Calculate Individual Valuation Factors
        $factors = [];
        $appliedMultipliers = [];

        // --- Factor A: Starting Price Base Anchor ---
        // Typical optimal reserve is 18% to 35% above starting bid
        $baseRatio = 1.25;
        $factors[] = [
            'name'        => 'Starting Bid Baseline',
            'value'       => 'Rs. ' . number_format($startingPrice, 2),
            'impact'      => '+25.0%',
            'type'        => 'anchor',
            'icon'        => 'bi-tag-fill',
            'color'       => '#059669',
            'description' => 'Establishes safety reserve above the opening bid to prevent undervaluation while attracting bidder interest.'
        ];

        // --- Factor B: Previous Similar Auctions (Comps) ---
        $compsCount = $compsData['count'];
        $avgCompsPrice = $compsData['avg_price'];
        $historicalRatio = $compsData['avg_reserve_ratio'] > 1.0 ? $compsData['avg_reserve_ratio'] : 1.22;

        if ($compsCount > 0) {
            $diffFromStart = (($avgCompsPrice - $startingPrice) / $startingPrice) * 100;
            $impactStr = ($diffFromStart >= 0 ? '+' : '') . number_format($diffFromStart, 1) . '%';
            $factors[] = [
                'name'        => 'Historical Similar Auctions',
                'value'       => $compsCount . ' comps (Avg Rs. ' . number_format($avgCompsPrice, 0) . ')',
                'impact'      => $impactStr,
                'type'        => $diffFromStart >= 0 ? 'positive' : 'negative',
                'icon'        => 'bi-clock-history',
                'color'       => '#2563eb',
                'description' => 'Real sales and bidding benchmark from ' . $compsCount . ' comparable items in ' . ($brand ? htmlspecialchars($brand) . ' / ' : '') . ($categoryInfo['name'] ?? 'catalog') . '.'
            ];
        } else {
            $factors[] = [
                'name'        => 'Historical Comps Benchmark',
                'value'       => 'Category Heuristic Model',
                'impact'      => 'Baseline',
                'type'        => 'neutral',
                'icon'        => 'bi-database-check',
                'color'       => '#64748b',
                'description' => 'Algorithmic heuristic estimation based on ' . ($categoryInfo['name'] ?? 'general category') . ' reserve velocity.'
            ];
        }

        // --- Factor C: Item Condition ---
        $conditionKey = strtolower($condition);
        $condMultiplier = $this->conditionMultipliers[$conditionKey] ?? 1.00;
        $condPct = ($condMultiplier - 1.0) * 100;
        $appliedMultipliers['condition'] = $condMultiplier;

        if (!empty($condition)) {
            $factors[] = [
                'name'        => 'Physical Condition (' . htmlspecialchars($condition) . ')',
                'value'       => htmlspecialchars($condition),
                'impact'      => ($condPct >= 0 ? '+' : '') . number_format($condPct, 1) . '%',
                'type'        => $condPct >= 0 ? 'positive' : 'negative',
                'icon'        => 'bi-shield-check',
                'color'       => $condPct >= 0 ? '#10b981' : '#f59e0b',
                'description' => $condPct >= 0
                    ? 'Pristine condition commands premium bidder confidence and lower buyer risk.'
                    : 'Condition discount applied to match realistic bidder expectations and prevent unsold lot.'
            ];
        }

        // --- Factor D: Brand Desirability Index ---
        $brandKey = strtolower($brand);
        $brandMultiplier = 1.00;
        foreach ($this->premiumBrands as $bKey => $bMult) {
            if (strpos($brandKey, $bKey) !== false) {
                $brandMultiplier = $bMult;
                break;
            }
        }
        $brandPct = ($brandMultiplier - 1.0) * 100;
        $appliedMultipliers['brand'] = $brandMultiplier;

        if (!empty($brand)) {
            $factors[] = [
                'name'        => 'Brand Market Desirability',
                'value'       => htmlspecialchars($brand),
                'impact'      => ($brandPct > 0 ? '+' : '') . number_format($brandPct, 1) . '%',
                'type'        => $brandPct > 0 ? 'positive' : 'neutral',
                'icon'        => 'bi-patch-check-fill',
                'color'       => '#8b5cf6',
                'description' => $brandPct > 0
                    ? 'High brand liquidity and secondary market resale confidence.'
                    : 'Standard market liquidity index for this manufacturer.'
            ];
        }

        // --- Factor E: Manufacture Year & Depreciation / Vintage ---
        $yearMultiplier = 1.00;
        $currentYear = (int)date('Y');
        if ($year && $year > 1900 && $year <= $currentYear + 2) {
            $age = max(0, $currentYear - $year);
            if (stripos($condition, 'antique') !== false || stripos($condition, 'vintage') !== false || $age >= 25) {
                // Collectible appreciation
                $vintageBoost = min(0.35, $age * 0.015);
                $yearMultiplier += $vintageBoost;
                $factors[] = [
                    'name'        => 'Year / Vintage Appreciation (' . $year . ')',
                    'value'       => $age . ' years vintage',
                    'impact'      => '+' . number_format($vintageBoost * 100, 1) . '%',
                    'type'        => 'positive',
                    'icon'        => 'bi-hourglass-split',
                    'color'       => '#d97706',
                    'description' => 'Rare antique / vintage status adds collectible scarcity value.'
                ];
            } else {

                if ($age === 0) {
                    $yearMultiplier = 1.02; 
                } elseif ($age === 1) {
                    $yearMultiplier = 0.98;
                } else {
                    $deprec = min(0.32, ($age - 1) * 0.04);
                    $yearMultiplier = max(0.68, 0.98 - $deprec);
                }
                $yearPct = ($yearMultiplier - 1.0) * 100;
                $factors[] = [
                    'name'        => 'Manufacture Year & Age (' . $year . ')',
                    'value'       => $age . ' years old',
                    'impact'      => ($yearPct >= 0 ? '+' : '') . number_format($yearPct, 1) . '%',
                    'type'        => $yearPct >= 0 ? 'positive' : 'negative',
                    'icon'        => 'bi-calendar-check',
                    'color'       => $yearPct >= 0 ? '#10b981' : '#64748b',
                    'description' => 'Adjusted for market lifecycle depreciation based on release year.'
                ];
            }
        }
        $appliedMultipliers['year'] = $yearMultiplier;

        // --- Factor F: Location & Regional Demand ---
        $cityKey = strtolower($city);
        $cityMultiplier = 1.00;
        foreach ($this->metroCities as $cKey => $cMult) {
            if (strpos($cityKey, $cKey) !== false) {
                $cityMultiplier = $cMult;
                break;
            }
        }
        $cityPct = ($cityMultiplier - 1.0) * 100;
        $appliedMultipliers['location'] = $cityMultiplier;

        if (!empty($city)) {
            $factors[] = [
                'name'        => 'Regional Location Demand',
                'value'       => htmlspecialchars($city),
                'impact'      => ($cityPct > 0 ? '+' : '') . number_format($cityPct, 1) . '%',
                'type'        => $cityPct > 0 ? 'positive' : 'neutral',
                'icon'        => 'bi-geo-alt-fill',
                'color'       => '#ef4444',
                'description' => $cityPct > 0
                    ? 'Major commercial hub with high purchasing power and dense local bidder network.'
                    : 'Standard regional auction demand pool.'
            ];
        }

        // --- Factor G: Watchers, Bids & Real-time Demand Velocity ---
        $watchers = (int)($existingItemData['watcher_count'] ?? $compsData['avg_watchers'] ?? 8);
        $existingBidsCount = (int)($existingItemData['bid_count'] ?? 0);
        $highestBid = (float)($existingItemData['highest_bid'] ?? 0);

        $demandScore = 1.00;
        $demandLevel = 'Moderate Demand';

        if ($watchers >= 25 || $existingBidsCount >= 10) {
            $demandScore = 1.08;
            $demandLevel = 'Very High Demand';
        } elseif ($watchers >= 12 || $existingBidsCount >= 4) {
            $demandScore = 1.04;
            $demandLevel = 'High Demand';
        } elseif ($watchers <= 3 && $existingBidsCount === 0) {
            $demandScore = 0.97;
            $demandLevel = 'Emerging Demand';
        }

        $demandPct = ($demandScore - 1.0) * 100;
        $factors[] = [
            'name'        => 'Demand Velocity & Watchers',
            'value'       => $watchers . ' Watchers' . ($existingBidsCount > 0 ? ' & ' . $existingBidsCount . ' Bids' : ''),
            'impact'      => ($demandPct >= 0 ? '+' : '') . number_format($demandPct, 1) . '%',
            'type'        => $demandPct >= 0 ? 'positive' : 'negative',
            'icon'        => 'bi-eye-fill',
            'color'       => '#0ea5e9',
            'description' => 'Real-time viewer engagement and bidder competition index (' . $demandLevel . ').'
        ];

        // --- Factor H: Previous Bids on this Item (if available) ---
        if ($highestBid > 0) {
            $factors[] = [
                'name'        => 'Current Highest Bid',
                'value'       => 'Rs. ' . number_format($highestBid, 2),
                'impact'      => 'Floor Guard',
                'type'        => 'anchor',
                'icon'        => 'bi-hammer',
                'color'       => '#059669',
                'description' => 'Active bids on lot guarantee that recommended reserve will not drop below verified bidder interest.'
            ];
        }

        // =========================================================================
        // 5. SYNTHESIS ENGINE: Calculate Recommended Reserve Price
        // =========================================================================

        // Baseline reserve from starting price
        $rawReserve = $startingPrice * $baseRatio;

        // If historical comps exist, blend comps average with starting price anchor
        if ($compsCount >= 3 && $avgCompsPrice > $startingPrice) {
            $compWeight = min(0.60, 0.25 + ($compsCount * 0.03));
            $rawReserve = ($rawReserve * (1.0 - $compWeight)) + ($avgCompsPrice * $compWeight * 0.95);
        }

        // Apply attribute multipliers
        $combinedMultiplier = $condMultiplier * $brandMultiplier * $yearMultiplier * $cityMultiplier * $demandScore;
        $finalCalculated = $rawReserve * $combinedMultiplier;

        // Floor at starting price (reserve should never be below starting price)
        if ($finalCalculated < $startingPrice * 1.05) {
            $finalCalculated = $startingPrice * 1.10;
        }

        // If there are existing bids, ensure reserve protects seller above highest bid
        if ($highestBid > 0 && $finalCalculated < $highestBid) {
            $finalCalculated = $highestBid * 1.05;
        }

        // Round to clean realistic monetary figures
        $recommendedPrice = $this->cleanRoundPrice($finalCalculated);

        // Price range tiers
        $conservativePrice = $this->cleanRoundPrice(max($startingPrice, $recommendedPrice * 0.90));
        $aggressivePrice   = $this->cleanRoundPrice($recommendedPrice * 1.14);
        $estimatedMarket   = $this->cleanRoundPrice($recommendedPrice * 1.08);

        // Calculate confidence score
        $confidence = 72;
        if (!empty($brand))       $confidence += 5;
        if (!empty($model))       $confidence += 4;
        if (!empty($condition))   $confidence += 5;
        if ($year !== null)       $confidence += 4;
        if (!empty($city))        $confidence += 4;
        if ($compsCount >= 3)     $confidence += 4;
        if ($compsCount >= 8)     $confidence += 3;
        $confidence = min(96, $confidence);

        $confidenceLevel = $confidence >= 88 ? 'High Confidence' : ($confidence >= 78 ? 'Good Confidence' : 'Moderate');

        return [
            'success' => true,
            'data'    => [
                'recommended_reserve'           => $recommendedPrice,
                'recommended_reserve_formatted' => 'Rs. ' . number_format($recommendedPrice, 2),
                'range' => [
                    'conservative'           => $conservativePrice,
                    'conservative_formatted' => 'Rs. ' . number_format($conservativePrice, 2),
                    'recommended'            => $recommendedPrice,
                    'recommended_formatted'  => 'Rs. ' . number_format($recommendedPrice, 2),
                    'aggressive'             => $aggressivePrice,
                    'aggressive_formatted'   => 'Rs. ' . number_format($aggressivePrice, 2),
                ],
                'estimated_market_value'           => $estimatedMarket,
                'estimated_market_value_formatted' => 'Rs. ' . number_format($estimatedMarket, 2),
                'confidence_score'                 => $confidence,
                'confidence_level'                 => $confidenceLevel,
                'demand_level'                     => $demandLevel,
                'comps_count'                      => $compsCount,
                'summary_text'                     => $this->buildSummaryText($recommendedPrice, $startingPrice, $compsCount, $brand, $condition, $demandLevel),
                'factors'                          => $factors
            ]
        ];
    }

    /**
     * Fetch Historical Comps from DB
     */
    private function fetchHistoricalComps(int $categoryId, string $brand, string $model, ?int $itemId): array
    {
        $result = [
            'count'             => 0,
            'avg_price'         => 0,
            'avg_reserve_ratio' => 1.25,
            'avg_watchers'      => 8
        ];
        try {
            $sql = "
                SELECT 
                    id, starting_price, reserve_price, current_price, watcher_count, bid_count
                FROM items
                WHERE is_deleted = 0
            ";
            $where = [];
            $binds = [];
            if ($itemId) {
                $where[] = "id != :itemId";
                $binds[':itemId'] = $itemId;
            }
            if ($categoryId > 0) {
                $where[] = "category_id = :categoryId";
                $binds[':categoryId'] = $categoryId;
            }
            if (!empty($brand)) {
                $where[] = "LOWER(brand) LIKE :brand";
                $binds[':brand'] = '%' . strtolower($brand) . '%';
            }

            if (!empty($where)) {
                $sql .= " AND " . implode(" AND ", $where);
            }

            $sql .= " ORDER BY id DESC LIMIT 40";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($binds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // If brand-specific query had 0 results, fallback to category-wide comps
            if (empty($rows) && $categoryId > 0 && !empty($brand)) {
                $fallbackSql = "SELECT id, starting_price, reserve_price, current_price, watcher_count, bid_count 
                                FROM items 
                                WHERE is_deleted = 0 AND category_id = :catId " . ($itemId ? "AND id != :itemId" : "") . "
                                ORDER BY id DESC LIMIT 30";
                $fallbackStmt = $this->pdo->prepare($fallbackSql);
                $fallbackBinds = [':catId' => $categoryId];
                if ($itemId) $fallbackBinds[':itemId'] = $itemId;
                $fallbackStmt->execute($fallbackBinds);
                $rows = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if (!empty($rows)) {
                $count = count($rows);
                $totalPrice = 0;
                $totalReserveRatios = 0;
                $validRatioCount = 0;
                $totalWatchers = 0;

                foreach ($rows as $r) {
                    $sPrice = (float)$r['starting_price'];
                    $cPrice = (float)$r['current_price'] > 0 ? (float)$r['current_price'] : $sPrice;
                    
                    // Filter extreme abnormal outliers (e.g. dummy test entries > 5x starting price)
                    if ($sPrice > 0 && $cPrice > $sPrice * 4.0) {
                        $cPrice = $sPrice * 1.5;
                    }

                    $totalPrice += $cPrice;
                    $totalWatchers += (int)$r['watcher_count'];

                    if ((float)$r['starting_price'] > 0 && (float)$r['reserve_price'] > 0) {
                        $ratio = (float)$r['reserve_price'] / (float)$r['starting_price'];
                        if ($ratio >= 1.0 && $ratio <= 3.0) {
                            $totalReserveRatios += $ratio;
                            $validRatioCount++;
                        }
                    }
                }

                $result['count'] = $count;
                $result['avg_price'] = $totalPrice / $count;
                $result['avg_watchers'] = max(4, (int)round($totalWatchers / $count));
                if ($validRatioCount > 0) {
                    $result['avg_reserve_ratio'] = $totalReserveRatios / $validRatioCount;
                }
            }
        } catch (Exception $e) {
            // Graceful fallback on query failure
            error_log('AiReservePriceService comps error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Fetch existing item real-time bids & stats
     */
    private function fetchExistingItemData(?int $itemId): array
    {
        $data = [
            'bid_count'     => 0,
            'highest_bid'   => 0,
            'watcher_count' => 0
        ];

        if (!$itemId) {
            return $data;
        }

        try {
            // Get item stats
            $iStmt = $this->pdo->prepare("SELECT bid_count, watcher_count, current_price FROM items WHERE id = :id LIMIT 1");
            $iStmt->execute([':id' => $itemId]);
            $item = $iStmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                $data['bid_count']     = (int)$item['bid_count'];
                $data['watcher_count'] = (int)$item['watcher_count'];
                $data['highest_bid']   = (float)$item['current_price'];
            }

            // Cross verify with bids table
            $bStmt = $this->pdo->prepare("SELECT COUNT(*) as c, MAX(bid_amount) as max_b FROM bids WHERE item_id = :id");
            $bStmt->execute([':id' => $itemId]);
            $bidsRow = $bStmt->fetch(PDO::FETCH_ASSOC);
            if ($bidsRow && (int)$bidsRow['c'] > 0) {
                $data['bid_count']   = (int)$bidsRow['c'];
                $data['highest_bid'] = max($data['highest_bid'], (float)$bidsRow['max_b']);
            }
        } catch (Exception $e) {
            error_log('AiReservePriceService fetchExistingItemData error: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Fetch Category Name
     */
    private function fetchCategoryInfo(int $categoryId): array
    {
        if ($categoryId <= 0) return ['name' => 'General'];
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $categoryId]);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            return $cat ?: ['name' => 'General'];
        } catch (Exception $e) {
            return ['name' => 'General'];
        }
    }

    /**
     * Clean Rounding for Currency
     */
    private function cleanRoundPrice(float $amount): float
    {
        if ($amount < 1000) {
            return round($amount / 10) * 10;
        } elseif ($amount < 10000) {
            return round($amount / 50) * 50;
        } elseif ($amount < 100000) {
            return round($amount / 500) * 500;
        } elseif ($amount < 1000000) {
            return round($amount / 1000) * 1000;
        } elseif ($amount < 10000000) {
            return round($amount / 25000) * 25000;
        } else {
            return round($amount / 50000) * 50000;
        }
    }

    /**
     * Summary text generator
     */
    private function buildSummaryText(float $reserve, float $starting, int $comps, string $brand, string $cond, string $demand): string
    {
        $buffer = 'AI recommends a reserve of Rs. ' . number_format($reserve, 0) . ' ';
        $buffer .= '(' . round((($reserve - $starting) / $starting) * 100) . '% above starting bid) ';
        if ($comps > 0) {
            $buffer .= 'synthesized from ' . $comps . ' comparable auctions in our database ';
        }
        if (!empty($brand)) {
            $buffer .= 'with ' . htmlspecialchars($brand) . ' market desirability ';
        }
        $buffer .= 'under ' . strtolower($demand) . '.';
        return $buffer;
    }
}
