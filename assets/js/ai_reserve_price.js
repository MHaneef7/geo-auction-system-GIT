/**
 * AI Dynamic Reserve Price Component
 * File: assets/js/ai_reserve_price.js
 * 
 * Provides interactive client-side functionality for calculating and applying
 * AI Recommended Reserve Price based on multi-factor market intelligence.
 */

window.AiReservePrice = (function() {
    'use strict';

    let currentData = null;
    let selectedTier = 'recommended';

    function init(options) {
        const triggerBtn       = document.getElementById(options.triggerBtnId || 'btnAiReserve');
        const container        = document.getElementById(options.containerId || 'aiReserveContainer');
        const reserveInput     = document.getElementById(options.reserveInputId || 'reservePrice');
        const startingInput    = document.getElementById(options.startingInputId || 'startingBid');
        const categorySelect   = document.getElementById(options.categorySelectId || 'itemCategory');
        const brandInput       = document.getElementById(options.brandInputId || 'brand');
        const modelInput       = document.getElementById(options.modelInputId || 'model');
        const yearInput        = document.getElementById(options.yearInputId || 'year');
        const conditionSelect  = document.getElementById(options.conditionSelectId || 'condition_type');
        const cityInput        = document.getElementById(options.cityInputId || 'city');
        const itemId           = options.itemId || null;
        const apiUrl           = options.apiUrl || '../ajax/ai_reserve_price.php';

        if (!triggerBtn || !container) {
            return;
        }

        triggerBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Validate starting price first
            const startingVal = parseFloat(startingInput ? startingInput.value : 0);
            if (!startingVal || startingVal <= 0) {
                if (startingInput) {
                    startingInput.focus();
                    startingInput.classList.add('is-invalid');
                    setTimeout(() => startingInput.classList.remove('is-invalid'), 2500);
                }
                alert('Please enter a valid Starting Bid (PKR) first so the AI can evaluate the reserve pricing floor.');
                return;
            }

            container.style.display = 'block';
            renderLoading(container);

            // Gather parameters
            const payload = {
                starting_price: startingVal,
                category_id:    categorySelect ? categorySelect.value : 0,
                brand:          brandInput ? brandInput.value : '',
                model:          modelInput ? modelInput.value : '',
                year:           yearInput ? yearInput.value : '',
                condition_type: conditionSelect ? conditionSelect.value : '',
                city:           cityInput ? cityInput.value : '',
                item_id:        itemId
            };

            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(res => {
                if (!res.success) {
                    renderError(container, res.message || 'Valuation failed. Please verify your inputs.');
                    return;
                }

                currentData = res.data;
                selectedTier = 'recommended';
                renderSuccess(container, currentData, reserveInput);
            })
            .catch(err => {
                console.error('AI Reserve calculation error:', err);
                renderError(container, 'Unable to connect to the AI valuation server. Please try again.');
            });
        });
    }

    function renderLoading(container) {
        container.innerHTML = `
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center my-3" style="background: #f0fdf4; border: 1.5px dashed #34d399 !important;">
                <div class="d-flex flex-column align-items-center justify-content-center py-3">
                    <div class="spinner-border text-success mb-3" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Analyzing...</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-cpu text-success me-1"></i> AI Valuation Engine Analyzing Market Data...
                    </h5>
                    <p class="text-muted small mb-0 max-w-md">
                        Cross-referencing historical auctions, brand desirability, condition depreciation, location index, and buyer demand velocity...
                    </p>
                </div>
            </div>
        `;
    }

    function renderError(container, message) {
        container.innerHTML = `
            <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center gap-3 shadow-sm rounded-3 my-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                <div class="flex-grow-1">
                    <strong>AI Valuation Notice:</strong> ${escapeHtml(message)}
                </div>
                <button type="button" class="btn-close" onclick="this.closest('.alert').remove()"></button>
            </div>
        `;
    }

    function renderSuccess(container, data, reserveInput) {
        const recPrice   = data.recommended_reserve;
        const consPrice  = data.range.conservative;
        const aggrPrice  = data.range.aggressive;
        const confScore  = data.confidence_score;
        const confLevel  = data.confidence_level;
        const demandLvl  = data.demand_level;
        const estMarket  = data.estimated_market_value_formatted;

        let factorsHtml = '';
        if (data.factors && data.factors.length > 0) {
            factorsHtml = data.factors.map(f => {
                let badgeClass = 'ai-impact-neutral';
                if (f.type === 'positive') badgeClass = 'ai-impact-positive';
                else if (f.type === 'negative') badgeClass = 'ai-impact-negative';
                else if (f.type === 'anchor') badgeClass = 'ai-impact-anchor';

                return `
                    <div class="col-md-6">
                        <div class="ai-factor-item d-flex align-items-start gap-3 h-100">
                            <div class="ai-factor-icon-wrap" style="background-color: ${f.color}15; color: ${f.color};">
                                <i class="bi ${f.icon}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark small">${escapeHtml(f.name)}</span>
                                    <span class="ai-impact-badge ${badgeClass}">${escapeHtml(f.impact)}</span>
                                </div>
                                <div class="text-secondary small mb-1">
                                    <strong class="text-dark">${escapeHtml(f.value)}</strong>
                                </div>
                                <div class="text-muted" style="font-size: 0.76rem; line-height: 1.3;">
                                    ${escapeHtml(f.description)}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        container.innerHTML = `
            <div class="ai-reserve-card my-3">
                <!-- Header -->
                <div class="ai-reserve-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="ai-pulse-dot"></span>
                        <h6 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
                            <i class="bi bi-stars text-warning"></i> AI Dynamic Reserve Price Valuation
                        </h6>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="ai-reserve-badge">
                            <i class="bi bi-shield-check"></i> ${escapeHtml(confLevel)} (${confScore}%)
                        </span>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 0.75rem;" onclick="document.getElementById('${container.id}').style.display='none'"></button>
                    </div>
                </div>

                <div class="p-4">
                    <!-- Main Recommendation Callout -->
                    <div class="row align-items-center g-3 mb-4">
                        <div class="col-lg-7">
                            <div class="d-flex align-items-baseline gap-2 mb-1">
                                <span class="text-secondary fw-semibold small text-uppercase letter-spacing-1">Recommended Reserve Price:</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small fw-semibold">
                                    <i class="bi bi-graph-up-arrow me-1"></i>${escapeHtml(demandLvl)}
                                </span>
                            </div>
                            <div class="ai-hero-price" id="aiHeroPriceDisplay">
                                ${data.recommended_reserve_formatted}
                            </div>
                            <p class="text-muted small mt-2 mb-0">
                                <i class="bi bi-info-circle text-primary me-1"></i> ${escapeHtml(data.summary_text)}
                            </p>
                        </div>

                        <div class="col-lg-5 text-lg-end">
                            <div class="bg-white p-3 rounded-3 border shadow-sm text-start mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small text-muted">Estimated Market Value:</span>
                                    <span class="fw-bold text-dark">${estMarket}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small text-muted">AI Confidence Score:</span>
                                    <span class="fw-bold text-success">${confScore}%</span>
                                </div>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: ${confScore}%" aria-valuenow="${confScore}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>

                            <button type="button" class="btn btn-success px-4 py-2 fw-semibold shadow-sm w-100 d-inline-flex align-items-center justify-content-center gap-2" id="btnApplyAiReserve">
                                <i class="bi bi-check2-circle fs-5"></i>
                                <span id="btnApplyAiReserveText">Apply Recommended Price (${data.recommended_reserve_formatted})</span>
                            </button>
                        </div>
                    </div>

                    <!-- 3 Dynamic Price Strategy Tiers -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small text-uppercase mb-2">Select Reserve Strategy Tier:</label>
                        <div class="row g-2">
                            <!-- Conservative -->
                            <div class="col-md-4">
                                <div class="ai-tier-card text-center" id="tierConservative" data-price="${consPrice}">
                                    <span class="ai-tier-badge bg-secondary text-white">Fast Sale</span>
                                    <div class="text-secondary small fw-bold">Conservative</div>
                                    <div class="fw-bold text-dark fs-5 mt-1">${data.range.conservative_formatted}</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Safe floor to ensure lot sells</div>
                                </div>
                            </div>

                            <!-- Recommended -->
                            <div class="col-md-4">
                                <div class="ai-tier-card text-center active" id="tierRecommended" data-price="${recPrice}">
                                    <span class="ai-tier-badge bg-success text-white">Optimal</span>
                                    <div class="text-success small fw-bold">Recommended</div>
                                    <div class="fw-bold text-success fs-5 mt-1">${data.recommended_reserve_formatted}</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Optimal profit / clearance ratio</div>
                                </div>
                            </div>

                            <!-- Aggressive -->
                            <div class="col-md-4">
                                <div class="ai-tier-card text-center" id="tierAggressive" data-price="${aggrPrice}">
                                    <span class="ai-tier-badge bg-danger text-white">Max Margin</span>
                                    <div class="text-danger small fw-bold">Aggressive</div>
                                    <div class="fw-bold text-dark fs-5 mt-1">${data.range.aggressive_formatted}</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Captures highest bidder surplus</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Multi-Factor Valuation Drivers -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-diagram-3-fill text-success"></i> Comprehensive Valuation Drivers (12 Factors Analyzed)
                            </h6>
                            <span class="badge bg-light text-secondary border">Real-time Data Intelligence</span>
                        </div>
                        <div class="row g-2">
                            ${factorsHtml}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Tier Card Click Listeners
        const tierCards = [
            { el: document.getElementById('tierConservative'), price: consPrice, formatted: data.range.conservative_formatted, label: 'Conservative' },
            { el: document.getElementById('tierRecommended'),  price: recPrice,  formatted: data.recommended_reserve_formatted, label: 'Recommended' },
            { el: document.getElementById('tierAggressive'),   price: aggrPrice, formatted: data.range.aggressive_formatted, label: 'Aggressive' }
        ];

        let activeSelectedPrice = recPrice;
        let activeSelectedFormatted = data.recommended_reserve_formatted;

        tierCards.forEach(t => {
            if (!t.el) return;
            t.addEventListener('click', function() {
                tierCards.forEach(item => item.el && item.el.classList.remove('active'));
                t.el.classList.add('active');
                activeSelectedPrice = t.price;
                activeSelectedFormatted = t.formatted;

                const heroDisplay = document.getElementById('aiHeroPriceDisplay');
                if (heroDisplay) heroDisplay.textContent = t.formatted;

                const applyBtnText = document.getElementById('btnApplyAiReserveText');
                if (applyBtnText) applyBtnText.textContent = `Apply ${t.label} Price (${t.formatted})`;
            });
        });

        // Apply Button Listener
        const btnApply = document.getElementById('btnApplyAiReserve');
        if (btnApply && reserveInput) {
            btnApply.addEventListener('click', function() {
                reserveInput.value = activeSelectedPrice;
                reserveInput.classList.remove('highlight-pulse');
                void reserveInput.offsetWidth; // Trigger reflow
                reserveInput.classList.add('highlight-pulse');

                btnApply.classList.remove('btn-success');
                btnApply.classList.add('btn-dark');
                btnApply.innerHTML = `<i class="bi bi-check2-all fs-5 text-success"></i> <span>Applied: ${activeSelectedFormatted} ✓</span>`;

                // Scroll smoothly to the input
                reserveInput.scrollIntoView({ behavior: 'smooth', block: 'center' });

                setTimeout(() => {
                    btnApply.classList.remove('btn-dark');
                    btnApply.classList.add('btn-success');
                    btnApply.innerHTML = `<i class="bi bi-check2-circle fs-5"></i> <span>Apply Price (${activeSelectedFormatted})</span>`;
                }, 2500);
            });
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    return {
        init: init
    };
})();
