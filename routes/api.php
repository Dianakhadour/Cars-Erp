<?php

use App\Models\User;
use App\Models\Tenant\Part;
use Illuminate\Http\Request;
use App\Models\Tenant\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\COAController;
use App\Http\Controllers\AuthController;
use Laravel\Sanctum\PersonalAccessToken;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\SequenceController;
use App\Http\Controllers\TenantAuthController;
use App\Http\Controllers\Inventory\PartController;
use App\Http\Controllers\Finance\ReportsController;
use App\Http\Controllers\Workshop\JobCardController;
use App\Http\Controllers\Inventory\PurchaseOrderLine;
use App\Http\Controllers\Inventory\VehicleController;
use App\Http\Controllers\Inventory\CategoryController;
use App\Http\Controllers\Inventory\CustomerController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\Inventory\PartStockController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\Inventory\StockQueryController;
use App\Http\Controllers\Inventory\TenantUserController;
use App\Http\Controllers\Inventory\PartPricingController;
use App\Http\Controllers\Finance\JournalEntriesController;
use App\Http\Controllers\Inventory\GoodsReceiptController;
use App\Http\Controllers\Inventory\PartCategoryController;
use App\Http\Controllers\Inventory\PartMovementController;
use App\Http\Controllers\Inventory\PartSupplierController;
use App\Http\Controllers\Finance\ChartOfAccountsController;
use App\Http\Controllers\Inventory\PurchaseOrderController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\VehicleStatusController;
use App\Http\Controllers\Inventory\InventoryReportController;
use App\Http\Controllers\Inventory\ProcurementReportController;
use App\Http\Controllers\Inventory\PurchaseOrderLineController;
use App\Http\Controllers\Inventory\PartsPurchaseOrderController;
use App\Http\Controllers\Inventory\WorkshopIntegrationController;
use App\Http\Controllers\Inventory\Report\ValuationReportController;
use App\Http\Controllers\Finance\ChartOfAccountIntegrationsController;
use App\Http\Controllers\Inventory\Finance\FinanceIntegrationController;
// Route::get('/test-connection', [TenantController::class, 'testConnection']);
// Route::get('/tenant-info', [TenantController::class, 'getTenantInfo']);
 
Route::middleware(['tenant.resolve'])->group(function () {
   
 // Tenant Public Auth Routes
 Route::prefix('auth')->group(function () {
    Route::post('/register-tenant', [TenantAuthController::class, 'register']);
    Route::post('/login-tenant', [TenantAuthController::class, 'login']);
});
});
 

Route::middleware(['tenant.resolve','tenant.jwt'])->group(function () {
   
        // License info route
        Route::get('/license-info', [CompanyController::class, 'getLicenseInfo']);
    
    
         // Companies CRUD  
         Route::prefix('companies')->group(function () {
          
            Route::get('/', [CompanyController::class, 'index']);
            Route::get('/{id}', [CompanyController::class, 'show']);
            Route::post('/', [CompanyController::class, 'store']);
            Route::put('/{id}', [CompanyController::class, 'update']);
            Route::delete('/{id}', [CompanyController::class, 'destroy']);
                
                // Company registrations
                Route::post('/{companyId}/registrations', [CompanyController::class, 'addRegistration']);
                Route::delete('/{companyId}/registrations/{registrationId}', [CompanyController::class, 'removeRegistration']);
          
    
            // COA routes
            Route::prefix('{companyId}/coa')->group(function () {
                
                Route::get('/', [COAController::class, 'index']);
                
              
                    Route::post('/', [COAController::class, 'store']);
                    Route::post('/import-default', [COAController::class, 'importDefault']);
                    Route::put('/{accountId}', [COAController::class, 'update']);
                    Route::delete('/{accountId}', [COAController::class, 'destroy']);
              
            });
    
            // Number sequences routes
            Route::prefix('{companyId}/sequences')->group(function () {
       
                Route::get('/', [SequenceController::class, 'index']);
                 
                    Route::post('/', [SequenceController::class, 'store']);
                    Route::put('/{sequenceId}', [SequenceController::class, 'update']);
                    Route::delete('/{sequenceId}', [SequenceController::class, 'destroy']);
                    Route::post('/{sequenceId}/generate-next', [SequenceController::class, 'generateNext']);
             
            });
    
            // Branches routes
            Route::prefix('{companyId}/branches')->group(function () {
                // View branches - available to all roles
                Route::get('/', [BranchController::class, 'index']);
                Route::get('/{branchId}', [BranchController::class, 'show']);
                  Route::post('/', [BranchController::class, 'store']);
                    Route::put('/{branchId}', [BranchController::class, 'update']);
                    Route::delete('/{branchId}', [BranchController::class, 'destroy']);
                
            });
       
    });
    Route::prefix('finance')->group(function () {
    //accounts
    Route::apiResource('accounts', ChartOfAccountsController::class);

    //integrations
    Route::get('/integrations/types', [ChartOfAccountIntegrationsController::class, 'getIntegrationTypes']);
    Route::post('/integrations/resolve-account', [ChartOfAccountIntegrationsController::class, 'resolveAccount']);
    Route::apiResource('integrations', ChartOfAccountIntegrationsController::class);
  
    //journal-entries
    Route::apiResource('journal-entries', JournalEntriesController::class)->only(['index', 'store', 'show']);
    Route::post('/journal-entries/{id}/post', [JournalEntriesController::class, 'postEntry']);
    Route::post('/journal-entries/{id}/reverse', [JournalEntriesController::class, 'reverseEntry']);

     // Reports
     Route::get('/reports', [ReportsController::class, 'listReports']);
     Route::post('/reports/{reportKey}/run', [ReportsController::class, 'runReport']);
     Route::post('/reports/{reportKey}/export/{format}', [ReportsController::class, 'exportReport']);

     //export reports
     Route::post('/finance/reports/{reportKey}/export/{format}', [ReportsController::class, 'exportReport'])
    ->where('format', 'csv|pdf|excel');
});


    Route::prefix('Inventory')->group(function () {
        Route::prefix('categories')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);
            Route::post('/', [CategoryController::class, 'store']);
            Route::get('/{id}', [CategoryController::class, 'show']);
            Route::put('/{id}', [CategoryController::class, 'update']);
            Route::delete('/{id}', [CategoryController::class, 'destroy']);
            Route::get('/type/{type}', [CategoryController::class, 'byType']);
        });
        Route::prefix('vehicles')->group(function () {
            Route::get('/', [VehicleController::class, 'index']);
            Route::post('/', [VehicleController::class, 'store']);
            Route::get('/search', [VehicleController::class, 'search']);
            
           
            Route::get('/status-summary', [VehicleStatusController::class, 'getStatusSummary']);
            Route::get('/status/{status}', [VehicleStatusController::class, 'getVehiclesByStatus']);
            
             
            Route::get('/{id}', [VehicleController::class, 'show']);
            Route::put('/{id}', [VehicleController::class, 'update']);
            Route::put('/{id}/status', [VehicleStatusController::class, 'updateStatus']);
        });
        Route::prefix('warehouses')->group(function () {
            Route::get('/', [WarehouseController::class, 'index']);
            Route::post('/', [WarehouseController::class, 'store']);
            Route::get('/{id}', [WarehouseController::class, 'show']);
            Route::put('/{id}/set-default', [WarehouseController::class, 'setDefault']);
        });
        // Stock Movement Routes
        Route::prefix('stock-movements')->group(function () {
            Route::get('/', [StockMovementController::class, 'index']);
            Route::post('/receipts', [StockMovementController::class, 'receiveStock']);
            Route::post('/issues', [StockMovementController::class, 'issueStock']);
            Route::post('/transfers', [StockMovementController::class, 'transferStock']);
            Route::post('/adjustments', [StockMovementController::class, 'adjustStock']);
        });

        // Stock Query Routes
        Route::prefix('stock')->group(function () {
            Route::get('/levels', [StockQueryController::class, 'getStockLevels']);
            Route::get('/vehicles/{id}/availability', [StockQueryController::class, 'checkVehicleAvailability']);
            Route::get('/warehouses/{id}/vehicles', [StockQueryController::class, 'getWarehouseVehicles']);
            Route::get('/summary', [StockQueryController::class, 'getStockSummary']);
        });
        Route::prefix('purchase-orders')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'index']);
            Route::post('/', [PurchaseOrderController::class, 'store']);
            Route::get('/{id}', [PurchaseOrderController::class, 'show']);
            Route::put('/{id}', [PurchaseOrderController::class, 'update']);
            Route::put('/{id}/status', [PurchaseOrderController::class, 'updateStatus']);
        });

        Route::prefix('purchase-orders/{id}/lines')->group(function () {
            Route::post('/', [PurchaseOrderLineController::class, 'store']);
            Route::put('/{lineId}', [PurchaseOrderLineController::class, 'update']);
            Route::delete('/{lineId}', [PurchaseOrderLineController::class, 'destroy']);
            Route::put('/', [PurchaseOrderLineController::class, 'bulkUpdate']); // Optional bulk update
        });
        Route::prefix('purchase-orders/{id}')->group(function () {
            Route::post('/receive', [GoodsReceiptController::class, 'receiveGoods']);
            Route::get('/receipts', [GoodsReceiptController::class, 'listReceipts']);
            Route::get('/receipts/{receiptId}', [GoodsReceiptController::class, 'getReceiptDetails']);
        });

        // Supplier Management Routes
        Route::prefix('suppliers')->group(function () {
            Route::get('/', [SupplierController::class, 'index']);
            Route::post('/', [SupplierController::class, 'store']);
            Route::get('/{id}', [SupplierController::class, 'show']);
        });

        // Procurement Reporting Routes
        Route::prefix('reports')->group(function () {
            Route::get('/purchase-orders', [ProcurementReportController::class, 'purchaseOrderReports']);
            Route::get('/supplier-performance', [ProcurementReportController::class, 'supplierPerformance']);
            Route::get('/procurement-dashboard', [ProcurementReportController::class, 'procurementDashboard']);
        });

        
        Route::prefix('parts')->group(function () {
            
            // Parts Stock Management Routes  
            Route::get('/stock-levels', [PartStockController::class, 'getStockLevels']);
            Route::get('/low-stock', [PartStockController::class, 'getLowStockAlerts']);
            Route::get('/out-of-stock', [PartStockController::class, 'getOutOfStockItems']);
            
            // Categories
            Route::get('/categories', [PartCategoryController::class, 'index']);
            Route::post('/categories', [PartCategoryController::class, 'store']);
            Route::get('/categories/{id}', [PartCategoryController::class, 'show']);
            Route::put('/categories/{id}', [PartCategoryController::class, 'update']);
            Route::delete('/categories/{id}', [PartCategoryController::class, 'destroy']);
            
            // Parts Search
            Route::get('/search', [PartController::class, 'search']);
            
            // Parts Stock Management with parameters
            Route::get('/{id}/stock-history', [PartStockController::class, 'getStockHistory']);
            Route::post('/{id}/adjust-stock', [PartStockController::class, 'adjustStock']);
            
            // Individual part operations  
            Route::get('/{id}', [PartController::class, 'show']);
            Route::put('/{id}', [PartController::class, 'update']);
            Route::delete('/{id}', [PartController::class, 'destroy']);
            
            // Parts CRUD  
            Route::get('/', [PartController::class, 'index']);
            Route::post('/', [PartController::class, 'store']);

            // Workshop Integration Routes
            
                Route::post('/reserve-for-job', [WorkshopIntegrationController::class, 'reserveForJob']);
                Route::post('/issue-to-job', [WorkshopIntegrationController::class, 'issueToJob']);
                Route::get('/job-usage/{job_id}', [WorkshopIntegrationController::class, 'getJobUsage']);
                Route::post('/return-from-job', [WorkshopIntegrationController::class, 'returnFromJob']);
  
          
        });
      
        Route::prefix('parts-movements')->group(function () {
            // CRUD
            Route::get('/', [PartMovementController::class, 'index']);
            Route::get('/{id}', [PartMovementController::class, 'show']);
            
            // Movement Operations
            Route::post('/receipts', [PartMovementController::class, 'receiveParts']);
            Route::post('/issues', [PartMovementController::class, 'issueParts']);
            Route::post('/transfers', [PartMovementController::class, 'transferParts']);
            Route::post('/adjustments', [PartMovementController::class, 'adjustStock']);
        });

        // Parts Procurement Routes
        Route::prefix('parts-purchase-orders')->group(function () {
            Route::get('/', [PartsPurchaseOrderController::class, 'index']);
            Route::post('/', [PartsPurchaseOrderController::class, 'store']);
            Route::get('/{id}', [PartsPurchaseOrderController::class, 'show']);
            Route::post('/{id}/receive', [PartsPurchaseOrderController::class, 'receiveOrder']);
        });
      

        // Inventory Valuation Reports
        Route::prefix('reports')->group(function () {
            Route::get('/valuation-summary', [ValuationReportController::class, 'getValuationSummary']);
            Route::get('/valuation-by-category', [ValuationReportController::class, 'getValuationByCategory']);
            Route::get('/valuation-by-warehouse', [ValuationReportController::class, 'getValuationByWarehouse']);
        });

        // Finance Integration
        Route::prefix('finance')->group(function () {
            Route::post('/export-stock-valuation', [FinanceIntegrationController::class, 'exportStockValuation']);
            Route::get('/cost-of-goods-sold', [FinanceIntegrationController::class, 'getCostOfGoodsSold']);
            Route::post('/period-end-closing', [FinanceIntegrationController::class, 'periodEndClosing']);
        });

        // Parts Pricing & Costing
        Route::get('/parts/{id}/pricing', [PartPricingController::class, 'getPricing']);
        Route::put('/parts/{id}/pricing', [PartPricingController::class, 'updatePricing']);
        Route::post('/parts/{id}/update-cost', [PartPricingController::class, 'updateCost']);

        // Essential Reports
        Route::get('/reports/parts-valuation', [InventoryReportController::class, 'partsValuation']);
        Route::get('/reports/parts-reorder-suggestions', [InventoryReportController::class, 'partsReorderSuggestions']);
        Route::get('/reports/parts-movement-summary', [InventoryReportController::class, 'partsMovementSummary']);

        // Supplier Management
        Route::get('/parts-suppliers', [PartSupplierController::class, 'index']);
        Route::post('/parts-suppliers', [PartSupplierController::class, 'store']);

    });
    // Job Card Routes
    Route::prefix('workshop')->group(function () {
        Route::prefix('job-cards')->group(function () {
            Route::get('/', [JobCardController::class, 'index']);
            Route::post('/', [JobCardController::class, 'store']);
            Route::get('/{id}', [JobCardController::class, 'show']);
            Route::put('/{id}', [JobCardController::class, 'update']);
            Route::delete('/{id}', [JobCardController::class, 'destroy']);
            Route::put('/{id}/status', [JobCardController::class, 'updateStatus']);
        });
    });
    Route::post('/add-tenant-user', [TenantUserController::class, 'addTenantUser']);
    Route::post('/add-customers', [CustomerController::class, 'addSampleCustomers']);
});
    



 // AUTH ROUTES - MASTER DB CONTEXT
 Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']); // Master DB
    Route::post('/register', [AuthController::class, 'register']); // Master DB
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']); // Master DB
        Route::post('/logout', [AuthController::class, 'logout']); // Master DB
    });
});

// TENANCY ROUTES - MASTER DB CONTEXT (Admin only)
Route::prefix('tenancy')->middleware(['auth:sanctum', 'admin.only'])->group(function () {
    Route::post('/create', [TenantController::class, 'store']); // Create tenant
    Route::post('/attach-domain', [TenantController::class, 'attachDomain']); // Attach domain
    Route::delete('/detach-domain', [TenantController::class, 'detachDomain']); //detachDomain
    Route::get('/domains', [TenantController::class, 'listDomains']);

    Route::prefix('licenses')->group(function () {
        Route::get('/', [LicenseController::class, 'index']);
        Route::post('/', [LicenseController::class, 'store']);
        Route::get('/{id}', [LicenseController::class, 'show']);
        Route::put('/{id}', [LicenseController::class, 'update']);
        Route::delete('/{id}', [LicenseController::class, 'destroy']);
        Route::get('/tenant/{tenantId}', [LicenseController::class, 'getTenantLicenses']);
    });

});
 
 
 
