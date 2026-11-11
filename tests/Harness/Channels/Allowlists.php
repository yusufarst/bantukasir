<?php

namespace Tests\Harness\Channels;

/**
 * The allowlists of the sensitive channels, per role (SECURITY_MODEL
 * SEC-CH-01, SEC-CH-02, SEC-PR-03; suite AUTH02; TASK-007 scope item 3): the
 * paths of every field that each channel may carry to each kind of account.
 * A path names a field by its keys from the top, with `*` for every item of
 * a list or every key of a map. ChannelInspector compares what a response
 * really carries with these lists, exactly: a field the list does not name is
 * a leak, and a field it names that is missing is a defect of the test data.
 *
 * Every later Task adds the pages and JSON reads it serves, per role, and the
 * secrets that must never appear in them (EXECUTION_CONTEXT section 10).
 */
final class Allowlists
{
    /** The kinds of account of the matrix, and a visitor without a sign-in session. */
    public const array ACTORS = ['owner', 'ops', 'cashier', 'combined', 'guest'];

    /**
     * SEC-CH-02: the shared props of a page of a signed-in user, the same
     * fields for every role — its identity, display name, roles and capability
     * flags, and the limits of its sign-in session — beside Inertia's own
     * validation-error channel (SEC-CH-03) and the once prop of the dictionary
     * (ADR-005), which the inspector checks apart.
     */
    public const array SHARED_SIGNED_IN = [
        'auth',
        'auth.user',
        'auth.user.id',
        'auth.user.displayName',
        'auth.user.roles',
        'auth.user.roles.*',
        'auth.user.capabilities',
        'auth.user.capabilities.*',
        'auth.session',
        'auth.session.idleLimit',
        'auth.session.idleEndsIn',
        'auth.session.absoluteEndsAt',
        'auth.session.absoluteEndsIn',
        'errors',
        // The recovery epoch of every command envelope (TASK-009).
        'recoveryEpoch',
        // The shop's name, initials and accent for the shell (TASK-010;
        // SEC-CH-02 as amended).
        'shop',
        'shop.name',
        'shop.initials',
        'shop.accent',
    ];

    /** The shared props before sign-in: nobody's data; `auth`, `recoveryEpoch` and `shop` are null (TASK-009, TASK-010). */
    public const array SHARED_GUEST = ['auth', 'errors', 'recoveryEpoch', 'shop'];

    /**
     * The page props of each Inertia page, per kind of account; `*` for every
     * kind that may open it.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const array PAGES = [
        'access/SignIn' => ['guest' => ['notice']],
        'access/SetPassword' => ['guest' => ['passwordSet']],
        'access/Account' => ['*' => ['account', 'account.displayName', 'account.email', 'account.roles', 'account.roles.*', 'account.owner', 'passwordChanged']],
        'access/ChangePassword' => ['*' => []],
        'shell/Error' => ['*' => ['state', 'retryAfter'], 'guest' => ['state', 'retryAfter']],
        // UXP-006 (TASK-009): no props; the list is built in the browser from
        // the references and RD-04.
        'commands/Requests' => ['*' => []],
        // UXP-601, UXP-607 and UXP-608, the Owner's alone (TASK-010): the
        // entries of the index, and the revisions, the earlier ones with at
        // least one.
        'access/Settings' => ['owner' => ['entries', 'entries.*', 'entries.*.id', 'entries.*.href']],
        'configuration/Profile' => ['owner' => [
            'current', 'current.id', 'current.number', 'current.shopName', 'current.address', 'current.contact', 'current.email', 'current.documentHeader',
            'current.documentFooter', 'current.documentIdentity', 'current.accentToken', 'current.logo', 'current.createdAt', 'current.actorDisplayName',
            'earlier', 'earlier.*', 'earlier.*.id', 'earlier.*.number', 'earlier.*.shopName', 'earlier.*.address', 'earlier.*.contact', 'earlier.*.email',
            'earlier.*.documentHeader', 'earlier.*.documentFooter', 'earlier.*.documentIdentity', 'earlier.*.accentToken', 'earlier.*.logo', 'earlier.*.createdAt',
            'earlier.*.actorDisplayName',
        ]],
        'configuration/ProfileEdit' => ['owner' => [
            'current', 'current.id', 'current.number', 'current.shopName', 'current.address', 'current.contact', 'current.email', 'current.documentHeader',
            'current.documentFooter', 'current.documentIdentity', 'current.accentToken', 'current.logo', 'current.createdAt', 'current.actorDisplayName',
            'accents', 'accents.*',
        ]],
        // The user pages, the Owner's alone (TASK-011). UXP-606 carries only whose
        // link it is for: never the link (SEC-ACC-05).
        'access/OwnerDetails' => ['owner' => ['account', 'account.displayName', 'account.email', 'account.version']],
        'access/Users' => ['owner' => ['users', 'users.*', 'users.*.id', 'users.*.displayName', 'users.*.email', 'users.*.roles', 'users.*.roles.*', 'users.*.status', 'users.*.holdsSession', 'filters', 'filters.status', 'filters.role']],
        // The cash pages (TASK-027): UXP-609 and UXP-610, the Owner's alone, the registers with the
        // name of the responsible user of an OPEN session; UXP-104, for every holder of CAP-21, the
        // registers available now, never a session, an amount or another user.
        'cash/Registers' => ['owner' => ['registers', 'registers.*', ...self::REGISTER_ROW]],
        'cash/RegisterForm' => ['owner' => ['register', 'register.id', 'register.code', 'register.label', 'register.active', 'register.version', 'register.openBy']],
        'cash/OpenSession' => ['*' => ['registers', 'registers.*', 'registers.*.id', 'registers.*.code', 'registers.*.label']],
        // The till and its payment view (TASK-029), for every holder of CAP-21 in its own OPEN session: the
        // view, the session's header, the onboarding freeze and the active categories of the search's
        // filter; never a product, a price or a cost, which RD-01 carries. The receipt, for its cashier and
        // the Owner: the snapshot and the shop identity it froze, never a cost (BR-COST-08).
        'sales/Till' => ['*' => ['view', 'session', 'session.id', 'session.number', 'session.register', 'postingFrozen', ...self::CATEGORIES]],
        'sales/Receipt' => ['*' => [
            'receipt', 'receipt.saleId', 'receipt.number', 'receipt.issuedAt', 'receipt.cashier', 'receipt.sessionNumber', 'receipt.method', 'receipt.total', 'receipt.tendered',
            'receipt.change', 'receipt.customerName', 'receipt.lines', 'receipt.lines.*', 'receipt.lines.*.lineNo', 'receipt.lines.*.sku', 'receipt.lines.*.name', 'receipt.lines.*.unit',
            'receipt.lines.*.quantity', 'receipt.lines.*.unitPrice', 'receipt.lines.*.lineTotal',
            'shop', 'shop.name', 'shop.identity', 'shop.address', 'shop.contact', 'shop.email', 'shop.header', 'shop.footer', 'shop.logo',
        ]],
        'access/UserCreate' => ['owner' => []],
        'access/User' => ['owner' => ['user', 'user.id', 'user.displayName', 'user.email', 'user.roles', 'user.roles.*', 'user.status', 'user.version', 'user.passwordSet', 'user.linkExpiresAt', 'user.superAdmin', 'user.self']],
        'access/UserEdit' => ['owner' => ['user', 'user.id', 'user.displayName', 'user.email', 'user.roles', 'user.roles.*', 'user.version']],
        'access/LinkHandover' => ['owner' => ['user', 'user.id', 'user.displayName']],
        // The catalogue pages (TASK-017), for every kind of account that CAP-04 opens
        // them to: never a cost, a valuation or a margin (BR-CAT-26). TASK-022 and TASK-025
        // add their sections to UXP-302.
        'catalog/Products' => ['*' => ['filters', 'filters.q', 'filters.category', 'filters.brand', 'filters.status', 'filters.page', 'filters.import', ...self::CATEGORIES, 'canPrintLabels']],
        // UXP-308 (TASK-019): the column list of DC-04.
        'catalog/Import' => ['*' => ['columns', 'columns.*', 'columns.*.header', 'columns.*.required', 'canManageCategories']],
        'catalog/ProductCreate' => ['*' => [...self::CATEGORIES, 'code']],
        'catalog/Product' => ['*' => [
            ...self::PRODUCT,
            'priceHistory', 'priceHistory.*', 'priceHistory.*.amount', 'priceHistory.*.startedAt', 'priceHistory.*.actorDisplayName',
            'barcodes', 'barcodes.*', 'barcodes.*.mappingId', 'barcodes.*.token', 'barcodes.*.kind', 'barcodes.*.active', 'barcodes.*.reasonClass',
            'canCorrectBarcode', 'canChangePhoto', 'canPrintLabels',
            // The section "Stok" (UXI-167; TASK-022), for a full holder of CAP-14: never a cost.
            'stock', 'stock.onHand', 'stock.minimum', 'stock.state',
        ]],
        // UXP-305, the Owner's alone (TASK-020): the product it opened from.
        'catalog/BarcodeCorrection' => ['owner' => ['product', 'product.id']],
        // UXP-306 and its print view (TASK-018): a label is the code, the name and the
        // SKU, never a cost or a price (BR-CAT-35).
        'catalog/Labels' => ['*' => ['product', 'product.id', 'product.name', 'barcodes', 'barcodes.*', 'barcodes.*.mappingId', 'barcodes.*.token']],
        'documents/LabelPrint' => ['*' => ['label', 'label.name', 'label.sku', 'label.code', 'label.count']],
        'catalog/ProductEdit' => ['*' => [...self::PRODUCT, ...self::CATEGORIES]],
        'catalog/Categories' => ['*' => ['filters', 'filters.status', ...self::CATEGORIES]],
        // The stock pages (TASK-022), for every kind of account that CAP-14 opens them to: no
        // cost in the list, the history or a movement; the movement's cost evidence for the
        // Owner alone (CAP-20).
        'inventory/Stock' => ['*' => ['filters', 'filters.q', 'filters.state', 'filters.category', 'filters.perPage', ...self::CATEGORIES, ...self::STOCK_LIST_PROP, 'postingFrozen']],
        'inventory/History' => ['*' => [
            'filters', 'filters.products', 'filters.products.*', 'filters.type', 'filters.from', 'filters.to', 'products', 'products.*', 'products.*.id', 'products.*.name',
            'products.*.sku', 'opener', 'postingFrozen',
            'history', 'history.rows', 'history.rows.*', 'history.rows.*.id', 'history.rows.*.productSequence', 'history.rows.*.recordedAt', 'history.rows.*.type',
            'history.rows.*.quantity', 'history.rows.*.baseUnit', 'history.rows.*.quantityPrecision', 'history.rows.*.product', 'history.rows.*.product.id',
            'history.rows.*.product.name', 'history.rows.*.product.sku', 'history.rows.*.actorDisplayName', 'history.rows.*.source', 'history.rows.*.source.kind',
            'history.rows.*.source.id', 'history.rows.*.source.at', 'history.nextCursor', 'history.previousCursor',
        ]],
        'inventory/Movement' => [
            // The Owner's: whether the onboarding freeze is on, for "Batalkan mutasi" (UXI-227; TASK-026).
            'owner' => [...self::MOVEMENT, 'costEvidence', 'costEvidence.evidenceId', 'costEvidence.status', 'costEvidence.total', 'costEvidence.zeroNote', 'costEvidence.corrections', 'postingFrozen'],
            'ops' => self::MOVEMENT,
            'combined' => self::MOVEMENT,
        ],
        // The receiving pages (TASK-022), for every kind of account that CAP-11 opens them to:
        // the purchase cost of each line (CAP-12, D-033, D-047) — since TASK-025 as it is now, with
        // the mark and the cost at confirmation; a zero's evidence or reason, the cost source and
        // the correction history for the Owner alone.
        'inventory/Receivings' => ['*' => [
            'filters', 'filters.from', 'filters.to', 'filters.q', 'postingFrozen',
            'receivings', 'receivings.rows', 'receivings.rows.*', 'receivings.rows.*.id', 'receivings.rows.*.confirmedAt', 'receivings.rows.*.reference',
            'receivings.rows.*.lineCount', 'receivings.rows.*.actorDisplayName', 'receivings.nextCursor', 'receivings.previousCursor',
        ]],
        'inventory/ReceivingCreate' => ['*' => ['postingFrozen', 'zeroAllowed', ...self::CATEGORIES]],
        'inventory/Receiving' => [
            'owner' => [
                ...self::RECEIVING, 'lines.*.zeroNote', 'lines.*.evidenceId', 'lines.*.correctionNo', 'lines.*.corrections', 'lines.*.corrections.*', 'lines.*.corrections.*.correctionNo',
                'lines.*.corrections.*.oldAmount', 'lines.*.corrections.*.newAmount', 'lines.*.corrections.*.zeroNote', 'lines.*.corrections.*.reason', 'lines.*.corrections.*.recordedAt',
                'lines.*.corrections.*.actorDisplayName',
                // "Batalkan mutasi" per line (UXI-250; TASK-026).
                'lines.*.reversed', 'postingFrozen',
            ],
            'ops' => self::RECEIVING,
            'combined' => self::RECEIVING,
        ],
        // The manual issue pages (TASK-023), for every kind of account that CAP-13 opens them to:
        // no cost, since an allocation is the Owner's figure of the reports (ADR-008 section 6).
        'inventory/Issues' => ['*' => [
            'filters', 'filters.from', 'filters.to', 'filters.reason', 'postingFrozen',
            'issues', 'issues.rows', 'issues.rows.*', 'issues.rows.*.id', 'issues.rows.*.confirmedAt', 'issues.rows.*.reasonCode',
            'issues.rows.*.lineCount', 'issues.rows.*.actorDisplayName', 'issues.nextCursor', 'issues.previousCursor',
        ]],
        'inventory/IssueCreate' => ['*' => ['postingFrozen']],
        // The Owner's also: "Batalkan mutasi" per line (UXI-266; TASK-026).
        'inventory/Issue' => [
            'owner' => [...self::ISSUE, 'lines.*.reversed', 'postingFrozen'],
            'ops' => self::ISSUE,
            'combined' => self::ISSUE,
        ],
        // UXP-411, the Owner's alone (TASK-026): the movement to reverse, its exact inverse and the
        // stock now and after, never a cost.
        'inventory/Reversal' => ['owner' => [
            'source', 'source.movementId', 'source.type', 'source.quantity', 'source.inverse', 'source.baseUnit', 'source.quantityPrecision', 'source.recordedAt',
            'source.product', 'source.product.id', 'source.product.name', 'source.product.sku', 'source.documentId', 'source.stockNow', 'source.stockAfter',
            'source.reversed', 'postingFrozen', 'opener',
        ]],
        // The cost pages, the Owner's alone (TASK-025; CAP-19, CAP-20): RP-503's worklist with the
        // product of its filter, RP-504's cost source with its history, RP-505's valuation, cost
        // sources and corrections. No other role opens them.
        'reporting/CostWorklist' => ['owner' => [
            'filters', 'filters.product', 'filters.perPage', 'product', 'product.id', 'product.name', 'product.sku',
            'worklist', 'worklist.rows', 'worklist.rows.*', 'worklist.rows.*.evidenceId', 'worklist.rows.*.product', 'worklist.rows.*.product.id', 'worklist.rows.*.product.name',
            'worklist.rows.*.product.sku', 'worklist.rows.*.sourceType', 'worklist.rows.*.recordedAt', 'worklist.rows.*.unknown', 'worklist.rows.*.marked', 'worklist.nextCursor',
            'worklist.previousCursor',
        ]],
        'costing/Correction' => ['owner' => [
            'source', 'source.evidenceId', 'source.product', 'source.product.id', 'source.product.name', 'source.product.sku', 'source.movementId', 'source.sourceType',
            'source.recordedAt', 'source.quantity', 'source.baseUnit', 'source.quantityPrecision', 'source.cost', 'source.cost.status', 'source.cost.amount', 'source.zeroNote',
            'source.confirmedCost', 'source.confirmedCost.status', 'source.confirmedCost.amount', 'source.correctionNo', 'source.marked',
            'corrections', 'corrections.*', 'corrections.*.correctionNo', 'corrections.*.oldAmount', 'corrections.*.newAmount', 'corrections.*.zeroNote', 'corrections.*.reason',
            'corrections.*.recordedAt', 'corrections.*.actorDisplayName', 'opener',
        ]],
        'costing/CostHistory' => ['owner' => [
            'product', 'product.id', 'product.name', 'product.sku', 'product.baseUnit', 'product.quantityPrecision',
            'valuation', 'valuation.status', 'valuation.value', 'valuation.averageCost', 'valuation.onHand', 'marked',
            'evidence', 'evidence.*', 'evidence.*.evidenceId', 'evidence.*.movementId', 'evidence.*.sourceType', 'evidence.*.recordedAt', 'evidence.*.quantity', 'evidence.*.cost',
            'evidence.*.cost.status', 'evidence.*.cost.amount', 'evidence.*.zeroNote', 'evidence.*.correctionNo', 'evidence.*.marked',
            'corrections', 'corrections.*', 'corrections.*.evidenceId', 'corrections.*.correctionNo', 'corrections.*.sourceType', 'corrections.*.sourceRecordedAt',
            'corrections.*.oldAmount', 'corrections.*.newAmount', 'corrections.*.zeroNote', 'corrections.*.reason', 'corrections.*.recordedAt', 'corrections.*.actorDisplayName', 'opener',
        ]],
        // UXP-410, the Owner's alone (TASK-024): the first line's product as RD-01 in the context
        // `adjustment` gives it, with its version and stock, never a cost.
        'inventory/AdjustmentCreate' => ['owner' => [
            'postingFrozen', 'opener',
            'product', 'product.id', 'product.name', 'product.sku', 'product.baseUnit', 'product.quantityPrecision', 'product.price', 'product.price.amount',
            'product.price.revisionId', 'product.active', 'product.thumbnail', 'product.activeBarcode', 'product.version', 'product.stock', 'product.stock.onHand',
            'product.stock.minimum', 'product.stock.state',
        ]],
    ];

    /** RD-02's answer, and the prop `stock` of RP-401 that holds its first page. */
    private const array STOCK_LIST = [
        'rows', 'rows.*', 'rows.*.id', 'rows.*.name', 'rows.*.sku', 'rows.*.baseUnit', 'rows.*.quantityPrecision', 'rows.*.onHand', 'rows.*.minimum', 'rows.*.state',
        'outCount', 'lowCount', 'asOf', 'nextCursor',
    ];

    private const array STOCK_LIST_PROP = [
        'stock', 'stock.rows', 'stock.rows.*', 'stock.rows.*.id', 'stock.rows.*.name', 'stock.rows.*.sku', 'stock.rows.*.baseUnit', 'stock.rows.*.quantityPrecision',
        'stock.rows.*.onHand', 'stock.rows.*.minimum', 'stock.rows.*.state', 'stock.outCount', 'stock.lowCount', 'stock.asOf', 'stock.nextCursor',
    ];

    /** One movement of RP-403. */
    private const array MOVEMENT = [
        'movement', 'movement.id', 'movement.type', 'movement.quantity', 'movement.baseUnit', 'movement.quantityPrecision', 'movement.recordedAt',
        'movement.product', 'movement.product.id', 'movement.product.name', 'movement.product.sku', 'movement.actorDisplayName', 'movement.reference',
        'movement.reasonCode', 'movement.source', 'movement.source.kind', 'movement.source.id', 'movement.source.at',
        // The reversal links (COPY_DECK section 9.4; TASK-026).
        'movement.reversedById', 'movement.reversedByAt', 'movement.reversesId', 'movement.reversesAt',
    ];

    /** One manual issue of RP-409 with its lines, never a cost. */
    private const array ISSUE = [
        'issue', 'issue.id', 'issue.confirmedAt', 'issue.reasonCode', 'issue.note', 'issue.lineCount', 'issue.actorDisplayName',
        'lines', 'lines.*', 'lines.*.lineNo', 'lines.*.product', 'lines.*.product.id', 'lines.*.product.name', 'lines.*.product.sku', 'lines.*.movementId',
        'lines.*.quantity', 'lines.*.baseUnit', 'lines.*.quantityPrecision',
    ];

    /** One document of RP-406 with its lines and their purchase cost. */
    private const array RECEIVING = [
        'document', 'document.id', 'document.confirmedAt', 'document.reference', 'document.documentDate', 'document.lineCount', 'document.actorDisplayName',
        'lines', 'lines.*', 'lines.*.lineNo', 'lines.*.product', 'lines.*.product.id', 'lines.*.product.name', 'lines.*.product.sku', 'lines.*.movementId',
        'lines.*.quantity', 'lines.*.baseUnit', 'lines.*.quantityPrecision', 'lines.*.cost', 'lines.*.cost.status', 'lines.*.cost.amount',
        // After the Owner's correction (D-047; TASK-025).
        'lines.*.corrected', 'lines.*.confirmedCost', 'lines.*.confirmedCost.status', 'lines.*.confirmedCost.amount',
    ];

    /** A register of UXP-609, with the responsible user of its OPEN session. */
    private const array REGISTER_ROW = ['registers.*.id', 'registers.*.code', 'registers.*.label', 'registers.*.active', 'registers.*.version', 'registers.*.openBy'];

    /** The categories of a catalogue page. */
    private const array CATEGORIES = ['categories', 'categories.*', 'categories.*.id', 'categories.*.name', 'categories.*.active', 'categories.*.version'];

    /** A product of UXP-302 and UXP-304. */
    private const array PRODUCT = [
        'product', 'product.id', 'product.sku', 'product.name', 'product.categoryId', 'product.categoryName', 'product.categoryActive', 'product.brand', 'product.baseUnit',
        'product.quantityPrecision', 'product.price', 'product.price.amount', 'product.price.revisionId', 'product.minimumStock', 'product.active', 'product.version', 'product.unitLocked', 'product.photo',
    ];

    /**
     * The JSON bodies of the reads and answers that are not pages, per route
     * name and kind of account.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const array JSON = [
        'password.confirmation' => ['*' => ['confirmed']],
        // RD-04 with its rows (TASK-008): the actor's own, and with scope=all,
        // which only the Owner gets, every user's with the user.
        'data.requests' => ['*' => ['rows', 'rows.*', 'rows.*.operation', 'rows.*.family', 'rows.*.sourceIntentId', 'rows.*.idempotencyKey', 'rows.*.epoch', 'rows.*.receivedAt', 'rows.*.outcome', 'nextCursor', 'previousCursor']],
        'data.requests.all' => ['owner' => ['rows', 'rows.*', 'rows.*.operation', 'rows.*.family', 'rows.*.sourceIntentId', 'rows.*.idempotencyKey', 'rows.*.epoch', 'rows.*.receivedAt', 'rows.*.outcome', 'rows.*.user', 'rows.*.user.id', 'rows.*.user.displayName', 'nextCursor', 'previousCursor']],
        // RC-50 answered COMMITTED (TASK-008), and a command's answer.
        'commands.resolve' => ['*' => ['outcome', 'operation', 'commandId', 'result', 'result.type', 'result.id', 'result.location']],
        'commands.committed' => ['*' => ['outcome', 'operation', 'commandId', 'repeated', 'result', 'result.type', 'result.id', 'result.location']],
        // The answer that committed RC-35 or RC-41, with its link, the only channel
        // that ever carries one (SEC-ACC-05; TASK-011).
        'commands.committed.link' => ['owner' => ['outcome', 'operation', 'commandId', 'repeated', 'result', 'result.type', 'result.id', 'result.location', 'link', 'link.url', 'link.expiresAt']],
        // RC-27 to RC-29 and their status check: the mapping with its code (TASK-016).
        'commands.committed.barcode' => ['*' => ['outcome', 'operation', 'commandId', 'repeated', 'result', 'result.type', 'result.id', 'result.number', 'result.location']],
        'commands.resolve.barcode' => ['*' => ['outcome', 'operation', 'commandId', 'result', 'result.type', 'result.id', 'result.number', 'result.location']],
        // RC-02 and its status check: the session with its number (TASK-027).
        'commands.committed.number' => ['*' => ['outcome', 'operation', 'commandId', 'repeated', 'result', 'result.type', 'result.id', 'result.number', 'result.location']],
        'commands.resolve.number' => ['*' => ['outcome', 'operation', 'commandId', 'result', 'result.type', 'result.id', 'result.number', 'result.location']],
        'commands.refused' => ['*' => ['outcome', 'code', 'messageKey', 'params', 'fields', 'lines']],
        // The refusal of a posting with the lines it concerns, each by its product (RT-07;
        // TASK-021).
        'commands.refused.lines' => ['*' => ['outcome', 'code', 'messageKey', 'params', 'fields', 'lines', 'lines.*', 'lines.*.productId']],
        // INSUFFICIENT_STOCK with each line concerned by its product and the stock available
        // (INTERACTION_CONTRACTS section 1.6; TASK-023).
        'commands.refused.stock' => ['*' => ['outcome', 'code', 'messageKey', 'params', 'fields', 'lines', 'lines.*', 'lines.*.productId', 'lines.*.available']],
        // PRICE_CHANGED of RC-01 with each changed line by its product, its price in force and that
        // price's revision (INTERACTION_CONTRACTS section 1.6; TASK-028).
        'commands.refused.price' => ['*' => ['outcome', 'code', 'messageKey', 'params', 'fields', 'lines', 'lines.*', 'lines.*.productId', 'lines.*.price', 'lines.*.price.amount', 'lines.*.price.revisionId']],
        // RD-03, the shell's indicators (TASK-009), with the stock-attention count of the
        // badge, null for a cashier (TASK-022).
        'data.shell' => ['*' => ['unresolvedRequests', 'stockAttention', 'asOf']],
        // RD-02, the stock list (TASK-022): no cost.
        'data.stock' => [
            'owner' => self::STOCK_LIST,
            'ops' => self::STOCK_LIST,
            'combined' => self::STOCK_LIST,
        ],
        // RD-01 in the context `receiving` (TASK-022): each row with its product's version, for
        // RC-07's expected versions.
        'data.products.search.receiving' => [
            'owner' => [...self::SEARCH_RESULT, 'rows.*.version'],
            'ops' => [...self::SEARCH_RESULT, 'rows.*.version'],
            'combined' => [...self::SEARCH_RESULT, 'rows.*.version'],
        ],
        // RD-01 in the context `issue` (TASK-023): the same, for RC-08's expected versions.
        'data.products.search.issue' => [
            'owner' => [...self::SEARCH_RESULT, 'rows.*.version'],
            'ops' => [...self::SEARCH_RESULT, 'rows.*.version'],
            'combined' => [...self::SEARCH_RESULT, 'rows.*.version'],
        ],
        // RD-01 in the context `adjustment`, the Owner's alone (TASK-024): the same, for RC-09's
        // expected versions.
        'data.products.search.adjustment' => ['owner' => [...self::SEARCH_RESULT, 'rows.*.version']],
        // RD-01 in the context `till` (TASK-028), for every holder of CAP-21: each row with its price
        // revision, which RC-01 expects as reviewed, and the stock that the viewer's cell of CAP-14
        // allows — the quantity alone for a cashier, never a version or a cost.
        'data.products.search.till' => [
            'owner' => self::SEARCH_RESULT,
            'cashier' => self::SEARCH_RESULT_AT_CHECKOUT,
            'combined' => self::SEARCH_RESULT,
        ],
        // RD-01 in the catalog context and RD-06 (TASK-015): the Owner's, the
        // Operations admin's and the combined account's, never a cost, a
        // valuation or a margin (BR-CAT-26). RD-01 carries the stock per role
        // (TASK-021); RD-06 carries none.
        'data.products.search' => [
            'owner' => self::SEARCH_RESULT,
            'ops' => self::SEARCH_RESULT,
            'combined' => self::SEARCH_RESULT,
        ],
        // RR-01 (TASK-019): the import, its checksum, and every row with its errors.
        'products.import.preview' => [
            'owner' => self::IMPORT_PREVIEW,
            'ops' => self::IMPORT_PREVIEW,
            'combined' => self::IMPORT_PREVIEW,
        ],
        'data.products.check' => [
            'owner' => self::CHECK_RESULT,
            'ops' => self::CHECK_RESULT,
            'combined' => self::CHECK_RESULT,
        ],
        // RD-05, the Owner's alone (TASK-020): the token moved once, so that it holds every field.
        'data.barcodes.lookup' => ['owner' => [
            'token', 'kind', 'activeProduct', 'activeProduct.id', 'activeProduct.name',
            'retirements', 'retirements.*', 'retirements.*.productId', 'retirements.*.productName', 'retirements.*.reasonClass', 'retirements.*.retiredAt', 'retirements.*.actorDisplayName',
            'mapping', 'mapping.productId', 'mapping.productName', 'mapping.state', 'correctable',
        ]],
    ];

    /** One page of RD-01. */
    private const array SEARCH_RESULT = [
        'rows', 'rows.*', 'rows.*.id', 'rows.*.name', 'rows.*.sku', 'rows.*.baseUnit', 'rows.*.quantityPrecision',
        'rows.*.price', 'rows.*.price.amount', 'rows.*.price.revisionId', 'rows.*.active', 'rows.*.thumbnail', 'rows.*.activeBarcode',
        'rows.*.stock', 'rows.*.stock.onHand', 'rows.*.stock.minimum', 'rows.*.stock.state',
        'page', 'perPage', 'hasMore',
    ];

    /** RD-01 in the till for a cashier, whose cell of CAP-14 is the stock needed at checkout (TASK-028). */
    private const array SEARCH_RESULT_AT_CHECKOUT = [
        'rows', 'rows.*', 'rows.*.id', 'rows.*.name', 'rows.*.sku', 'rows.*.baseUnit', 'rows.*.quantityPrecision',
        'rows.*.price', 'rows.*.price.amount', 'rows.*.price.revisionId', 'rows.*.active', 'rows.*.thumbnail', 'rows.*.activeBarcode',
        'rows.*.stock', 'rows.*.stock.onHand',
        'page', 'perPage', 'hasMore',
    ];

    /** RD-06 with an exact barcode, an exact SKU and a similar name. */
    private const array IMPORT_PREVIEW = [
        'importId', 'fileSha256', 'dataRowCount', 'readyRows', 'errorRows',
        'rows', 'rows.*', 'rows.*.row', 'rows.*.sku', 'rows.*.name', 'rows.*.category', 'rows.*.sellingPrice',
        'rows.*.errors', 'rows.*.errors.*', 'rows.*.errors.*.key', 'rows.*.errors.*.params', 'rows.*.errors.*.params.kolom',
    ];

    private const array CHECK_RESULT = [
        'barcode', 'barcode.id', 'barcode.name', 'barcode.sku', 'barcode.baseUnit', 'barcode.quantityPrecision', 'barcode.price', 'barcode.price.amount', 'barcode.price.revisionId', 'barcode.active', 'barcode.thumbnail', 'barcode.activeBarcode',
        'sku', 'sku.id', 'sku.name', 'sku.sku', 'sku.baseUnit', 'sku.quantityPrecision', 'sku.price', 'sku.price.amount', 'sku.price.revisionId', 'sku.active', 'sku.thumbnail', 'sku.activeBarcode',
        'similarNames', 'similarNames.*', 'similarNames.*.id', 'similarNames.*.name', 'similarNames.*.sku', 'similarNames.*.baseUnit', 'similarNames.*.quantityPrecision',
        'similarNames.*.price', 'similarNames.*.price.amount', 'similarNames.*.price.revisionId', 'similarNames.*.active', 'similarNames.*.thumbnail', 'similarNames.*.activeBarcode',
    ];

    /**
     * The paths a page may carry for this kind of account, or null when the
     * page is not one this kind may open.
     *
     * @return list<string>|null
     */
    public static function page(string $component, string $actor): ?array
    {
        return self::pick(self::PAGES[$component] ?? null, $actor);
    }

    /**
     * @return list<string>|null
     */
    public static function json(string $route, string $actor): ?array
    {
        return self::pick(self::JSON[$route] ?? null, $actor);
    }

    /**
     * The list of this kind of account, or the list of every signed-in kind
     * (`*`), which a visitor without a sign-in session never gets.
     *
     * @param  array<string, list<string>>|null  $lists
     * @return list<string>|null
     */
    private static function pick(?array $lists, string $actor): ?array
    {
        if ($lists === null) {
            return null;
        }

        if (array_key_exists($actor, $lists)) {
            return $lists[$actor];
        }

        return $actor === 'guest' || ! array_key_exists('*', $lists) ? null : $lists['*'];
    }
}
