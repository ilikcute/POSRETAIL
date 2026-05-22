<?php

namespace App\Enums;

enum PermissionsEnum: string
{
    // ── User Management ─────────────────────────────────────────────────────
    case ViewUsers = 'view users';
    case CreateUsers = 'create users';
    case EditUsers = 'edit users';
    case DeleteUsers = 'delete users';

    // ── Role & Permission Management ─────────────────────────────────────────
    case ViewRoles = 'view roles';
    case CreateRoles = 'create roles';
    case EditRoles = 'edit roles';
    case DeleteRoles = 'delete roles';

    // ── Master: Product & Catalogue ──────────────────────────────────────────
    case ViewProducts = 'view products';
    case CreateProducts = 'create products';
    case EditProducts = 'edit products';
    case DeleteProducts = 'delete products';

    case ViewCategories = 'view categories';
    case CreateCategories = 'create categories';
    case EditCategories = 'edit categories';
    case DeleteCategories = 'delete categories';

    case ViewBrands = 'view brands';
    case CreateBrands = 'create brands';
    case EditBrands = 'edit brands';
    case DeleteBrands = 'delete brands';

    case ViewUnits = 'view units';
    case CreateUnits = 'create units';
    case EditUnits = 'edit units';
    case DeleteUnits = 'delete units';

    case ViewProductVariants = 'view product variants';
    case CreateProductVariants = 'create product variants';
    case EditProductVariants = 'edit product variants';
    case DeleteProductVariants = 'delete product variants';

    // ── Master: CRM ──────────────────────────────────────────────────────────
    case ViewCustomers = 'view customers';
    case CreateCustomers = 'create customers';
    case EditCustomers = 'edit customers';
    case DeleteCustomers = 'delete customers';

    case ViewSuppliers = 'view suppliers';
    case CreateSuppliers = 'create suppliers';
    case EditSuppliers = 'edit suppliers';
    case DeleteSuppliers = 'delete suppliers';

    // ── Master: Infrastructure ───────────────────────────────────────────────
    case ViewStations = 'view stations';
    case CreateStations = 'create stations';
    case EditStations = 'edit stations';
    case DeleteStations = 'delete stations';

    case ViewStores = 'view stores';
    case CreateStores = 'create stores';
    case EditStores = 'edit stores';
    case DeleteStores = 'delete stores';

    case ViewWarehouses = 'view warehouses';
    case CreateWarehouses = 'create warehouses';
    case EditWarehouses = 'edit warehouses';
    case DeleteWarehouses = 'delete warehouses';

    case ViewRacks = 'view racks';
    case CreateRacks = 'create racks';
    case EditRacks = 'edit racks';
    case DeleteRacks = 'delete racks';

    // ── Sales ────────────────────────────────────────────────────────────────
    case ViewSales = 'view sales';
    case CreateSales = 'create sales';
    case EditSales = 'edit sales';
    case VoidSales = 'void sales';

    case ViewPromotions = 'view promotions';
    case CreatePromotions = 'create promotions';
    case EditPromotions = 'edit promotions';
    case DeletePromotions = 'delete promotions';

    case ViewShifts = 'view shifts';
    case ManageShifts = 'manage shifts';

    case ViewLoyalty = 'view loyalty';
    case ManageLoyalty = 'manage loyalty';

    case ViewSuspendedCarts = 'view suspended carts';
    case ManageSuspendedCarts = 'manage suspended carts';

    // ── Purchase ─────────────────────────────────────────────────────────────
    case ViewPurchases = 'view purchases';
    case CreatePurchases = 'create purchases';
    case EditPurchases = 'edit purchases';
    case DeletePurchases = 'delete purchases';

    // ── Inventory ────────────────────────────────────────────────────────────
    case ViewInventory = 'view inventory';
    case ViewStockOpnames = 'view stock opnames';
    case CreateStockOpnames = 'create stock opnames';
    case EditStockOpnames = 'edit stock opnames';

    case ViewStockDisposals = 'view stock disposals';
    case CreateStockDisposals = 'create stock disposals';

    // ── Finance ──────────────────────────────────────────────────────────────
    case ViewAccounts = 'view accounts';
    case CreateAccounts = 'create accounts';
    case EditAccounts = 'edit accounts';
    case DeleteAccounts = 'delete accounts';

    case ViewJournalEntries = 'view journal entries';
    case CreateJournalEntries = 'create journal entries';

    case ViewFinancialReports = 'view financial reports';

    case ViewDailyClose = 'view daily close';
    case ManageDailyClose = 'manage daily close';

    case ViewMonthEnd = 'view month end';
    case ManageMonthEnd = 'manage month end';

    case ViewCashTransactions = 'view cash transactions';
    case CreateCashTransactions = 'create cash transactions';

    case ViewDebtLedger = 'view debt ledger';
    case ManageDebtLedger = 'manage debt ledger';

    // ── Settings ─────────────────────────────────────────────────────────────
    case ViewSettings = 'view settings';
    case EditSettings = 'edit settings';
}
