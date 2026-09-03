<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientAuthController;
use App\Http\Controllers\Api\ClientInviteController;
use App\Http\Controllers\Api\ClientProjectController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CustomerEntryController;
use App\Http\Controllers\Api\DesignController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\HandoverController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PartyController;
use App\Http\Controllers\Api\ProcurementController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectManagerController;
use App\Http\Controllers\Api\ProjectMaterialController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\ProjectReportController;
use App\Http\Controllers\Api\ProjectRequestController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\VendorAuthController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\VendorPortfolioController;
use App\Http\Controllers\Api\VendorProjectController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\WorkTypeController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/db-status', [AuthController::class, 'dbStatus']);

Route::post('/vendor/register', [VendorAuthController::class, 'register']);
Route::post('/vendor/login', [VendorAuthController::class, 'login']);

Route::post('/client/login', [ClientAuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::get('/client/projects', [ClientProjectController::class, 'index']);
    Route::get('/client/projects/{project}', [ClientProjectController::class, 'show']);
    Route::get('/client/projects/{project}/design', [ClientProjectController::class, 'designPackage']);
    Route::post('/client/projects/{project}/design/approve', [ClientProjectController::class, 'approveDesign']);
    Route::post('/client/projects/{project}/design/reject', [ClientProjectController::class, 'rejectDesign']);
    Route::post('/client/projects/{project}/design/plans/{plan}/comments', [ClientProjectController::class, 'storePlanComment']);
    Route::post('/client/projects/{project}/handover/sign-off', [ClientProjectController::class, 'handoverSignOff']);
    Route::get('/client/projects/{project}/requests', [ProjectRequestController::class, 'indexForClient']);
    Route::get('/client/projects/{project}/requests/{projectRequest}', [ProjectRequestController::class, 'showForClient']);
    Route::post('/client/projects/{project}/requests/{projectRequest}/approve', [ProjectRequestController::class, 'approve']);
    Route::post('/client/projects/{project}/requests/{projectRequest}/reject', [ProjectRequestController::class, 'reject']);
    Route::post('/client/projects/{project}/requests/{projectRequest}/comments', [ProjectRequestController::class, 'storeComment']);

    Route::get('/vendor/projects', [VendorProjectController::class, 'index']);
    Route::get('/vendor/projects/{project}', [VendorProjectController::class, 'show']);
    Route::get('/vendor/projects/{project}/requests', [ProjectRequestController::class, 'indexForVendor']);
    Route::get('/vendor/projects/{project}/requests/{projectRequest}', [ProjectRequestController::class, 'showForVendor']);
    Route::post('/vendor/projects/{project}/requests/{projectRequest}/comments', [ProjectRequestController::class, 'storeComment']);
    Route::post('/vendor/projects/{project}/requests/{projectRequest}/submit', [ProjectRequestController::class, 'submitResponse']);

    Route::get('/company', [CompanyController::class, 'show']);
    Route::put('/company', [CompanyController::class, 'update']);

    Route::get('/customers', [PartyController::class, 'index'])->defaults('type', 'customer');
    Route::post('/customers', [PartyController::class, 'store'])->defaults('type', 'customer');
    Route::get('/contractors', [PartyController::class, 'index'])->defaults('type', 'contractor');
    Route::post('/contractors', [PartyController::class, 'store'])->defaults('type', 'contractor');
    Route::apiResource('parties', PartyController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/work-types', [WorkTypeController::class, 'index']);
    Route::post('/work-types', [WorkTypeController::class, 'store']);
    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index']);
    Route::post('/expense-categories', [ExpenseCategoryController::class, 'store']);

    Route::get('/customer-entries', [CustomerEntryController::class, 'index']);
    Route::post('/customer-entries', [CustomerEntryController::class, 'store']);
    Route::get('/customers/{customer}/statement', [CustomerEntryController::class, 'statement']);

    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::get('/reports/expenses', [ExpenseController::class, 'byCategory']);

    Route::get('/jobs', [JobController::class, 'index']);
    Route::post('/jobs', [JobController::class, 'store']);
    Route::post('/jobs/{job}/payments', [JobController::class, 'pay']);

    Route::get('/reports/customers', [ReportController::class, 'customers']);
    Route::get('/reports/contractors', [ReportController::class, 'contractors']);
    Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement'])
        ->middleware('admin');

    // Ecosystem
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);

    Route::get('/projects/{project}/members', [ProjectMemberController::class, 'index']);
    Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store']);
    Route::delete('/projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy']);
    Route::post('/customers/{customer}/invite-client', [ClientInviteController::class, 'invite']);

    Route::get('/projects/{project}/requests', [ProjectRequestController::class, 'indexForCompany']);
    Route::post('/projects/{project}/requests', [ProjectRequestController::class, 'store']);
    Route::get('/projects/{project}/requests/{projectRequest}', [ProjectRequestController::class, 'showForCompany']);
    Route::post('/projects/{project}/requests/{projectRequest}/comments', [ProjectRequestController::class, 'storeComment']);
    Route::post('/projects/{project}/requests/{projectRequest}/approve', [ProjectRequestController::class, 'approve']);
    Route::post('/projects/{project}/requests/{projectRequest}/reject', [ProjectRequestController::class, 'reject']);

    Route::get('/projects/{project}/reports/summary', [ProjectReportController::class, 'summary']);

    Route::get('/vendors', [VendorController::class, 'index']);
    Route::get('/vendors/{vendor}', [VendorController::class, 'show']);

    Route::get('/vendor/portfolio', [VendorPortfolioController::class, 'index']);
    Route::post('/vendor/portfolio', [VendorPortfolioController::class, 'store']);
    Route::put('/vendor/portfolio/{portfolioItem}', [VendorPortfolioController::class, 'update']);
    Route::delete('/vendor/portfolio/{portfolioItem}', [VendorPortfolioController::class, 'destroy']);

    Route::post('/media', [MediaController::class, 'store']);

    Route::get('/product-categories', [ProductCategoryController::class, 'index']);
    Route::post('/product-categories', [ProductCategoryController::class, 'store']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/vendor/products', [ProductController::class, 'vendorIndex']);
    Route::post('/vendor/products', [ProductController::class, 'store']);
    Route::put('/vendor/products/{product}', [ProductController::class, 'update']);
    Route::delete('/vendor/products/{product}', [ProductController::class, 'destroy']);

    Route::get('/projects/{project}/materials', [ProjectMaterialController::class, 'index']);
    Route::post('/projects/{project}/materials', [ProjectMaterialController::class, 'store']);
    Route::post('/projects/{project}/materials/generate-po', [ProjectMaterialController::class, 'generatePo']);
    Route::put('/projects/{project}/materials/{line}', [ProjectMaterialController::class, 'update']);
    Route::delete('/projects/{project}/materials/{line}', [ProjectMaterialController::class, 'destroy']);

    Route::get('/quotes', [QuoteController::class, 'index']);
    Route::post('/quotes', [QuoteController::class, 'store']);
    Route::get('/quotes/{quote}', [QuoteController::class, 'show']);
    Route::post('/quotes/{quote}/respond', [QuoteController::class, 'respond']);
    Route::post('/quotes/{quote}/accept', [QuoteController::class, 'accept']);
    Route::post('/quotes/{quote}/reject', [QuoteController::class, 'reject']);

    Route::get('/projects/{project}/reviews', [ReviewController::class, 'index']);
    Route::post('/projects/{project}/reviews', [ReviewController::class, 'store']);

    Route::get('/projects/{project}/design/boards', [DesignController::class, 'boards']);
    Route::post('/projects/{project}/design/boards', [DesignController::class, 'storeBoard']);
    Route::put('/projects/{project}/design/boards/{board}', [DesignController::class, 'updateBoard']);
    Route::delete('/projects/{project}/design/boards/{board}', [DesignController::class, 'destroyBoard']);
    Route::post('/projects/{project}/design/boards/{board}/inspiration', [DesignController::class, 'storeInspiration']);
    Route::put('/projects/{project}/design/boards/{board}/inspiration/{inspiration}', [DesignController::class, 'updateInspiration']);
    Route::delete('/projects/{project}/design/boards/{board}/inspiration/{inspiration}', [DesignController::class, 'destroyInspiration']);
    Route::get('/projects/{project}/design/plans', [DesignController::class, 'plans']);
    Route::post('/projects/{project}/design/plans', [DesignController::class, 'storePlan']);
    Route::put('/projects/{project}/design/plans/{plan}', [DesignController::class, 'updatePlan']);
    Route::delete('/projects/{project}/design/plans/{plan}', [DesignController::class, 'destroyPlan']);
    Route::post('/projects/{project}/design/plans/{plan}/submit', [DesignController::class, 'submitPlan']);
    Route::post('/projects/{project}/design/plans/{plan}/approve', [DesignController::class, 'approvePlan']);
    Route::post('/projects/{project}/design/plans/{plan}/reject', [DesignController::class, 'rejectPlan']);
    Route::get('/projects/{project}/design/plans/{plan}/comments', [DesignController::class, 'planComments']);
    Route::post('/projects/{project}/design/plans/{plan}/comments', [DesignController::class, 'storePlanComment']);
    // Deprecated aliases
    Route::get('/projects/{project}/design/floor-plans', [DesignController::class, 'floorPlans']);
    Route::post('/projects/{project}/design/floor-plans', [DesignController::class, 'storeFloorPlan']);
    Route::put('/projects/{project}/design/floor-plans/{floorPlan}', [DesignController::class, 'updateFloorPlan']);
    Route::delete('/projects/{project}/design/floor-plans/{floorPlan}', [DesignController::class, 'destroyFloorPlan']);
    Route::get('/projects/{project}/design/boq', [DesignController::class, 'boq']);
    Route::post('/projects/{project}/design/boq', [DesignController::class, 'storeBoqLine']);
    Route::post('/projects/{project}/design/boq/from-inspiration', [DesignController::class, 'storeBoqFromInspiration']);
    Route::put('/projects/{project}/design/boq/{boqLine}', [DesignController::class, 'updateBoqLine']);
    Route::delete('/projects/{project}/design/boq/{boqLine}', [DesignController::class, 'destroyBoqLine']);
    Route::post('/projects/{project}/design/submit-to-client', [DesignController::class, 'submitToClient']);
    Route::post('/projects/{project}/design/approve', [DesignController::class, 'approveDesign']);
    Route::post('/projects/{project}/design/boq/export-materials', [DesignController::class, 'exportMaterials']);

    Route::get('/projects/{project}/pm/tasks', [ProjectManagerController::class, 'tasks']);
    Route::post('/projects/{project}/pm/tasks', [ProjectManagerController::class, 'storeTask']);
    Route::put('/projects/{project}/pm/tasks/{task}', [ProjectManagerController::class, 'updateTask']);
    Route::delete('/projects/{project}/pm/tasks/{task}', [ProjectManagerController::class, 'destroyTask']);
    Route::get('/projects/{project}/pm/milestones', [ProjectManagerController::class, 'milestones']);
    Route::post('/projects/{project}/pm/milestones', [ProjectManagerController::class, 'storeMilestone']);
    Route::put('/projects/{project}/pm/milestones/{milestone}', [ProjectManagerController::class, 'updateMilestone']);
    Route::delete('/projects/{project}/pm/milestones/{milestone}', [ProjectManagerController::class, 'destroyMilestone']);
    Route::get('/projects/{project}/pm/timeline', [ProjectManagerController::class, 'timeline']);
    Route::post('/projects/{project}/pm/timeline', [ProjectManagerController::class, 'storeTimelineEvent']);
    Route::put('/projects/{project}/pm/timeline/{timelineEvent}', [ProjectManagerController::class, 'updateTimelineEvent']);
    Route::delete('/projects/{project}/pm/timeline/{timelineEvent}', [ProjectManagerController::class, 'destroyTimelineEvent']);
    Route::get('/projects/{project}/pm/budget', [ProjectManagerController::class, 'budgetSummary']);
    Route::post('/projects/{project}/pm/budget', [ProjectManagerController::class, 'storeBudgetLine']);
    Route::put('/projects/{project}/pm/budget/{budgetLine}', [ProjectManagerController::class, 'updateBudgetLine']);
    Route::delete('/projects/{project}/pm/budget/{budgetLine}', [ProjectManagerController::class, 'destroyBudgetLine']);

    Route::get('/purchase-orders', [ProcurementController::class, 'index']);
    Route::post('/purchase-orders', [ProcurementController::class, 'store']);
    Route::get('/purchase-orders/{purchaseOrder}', [ProcurementController::class, 'show']);
    Route::put('/purchase-orders/{purchaseOrder}', [ProcurementController::class, 'update']);
    Route::post('/purchase-orders/{purchaseOrder}/approve', [ProcurementController::class, 'approve']);
    Route::post('/purchase-orders/{purchaseOrder}/receive', [ProcurementController::class, 'receive']);

    Route::get('/warehouses', [WarehouseController::class, 'index']);
    Route::post('/warehouses', [WarehouseController::class, 'store']);
    Route::get('/warehouses/{warehouse}/stock', [WarehouseController::class, 'stock']);
    Route::get('/warehouses/{warehouse}/movements', [WarehouseController::class, 'movements']);
    Route::post('/warehouses/{warehouse}/movements', [WarehouseController::class, 'storeMovement']);
    Route::get('/projects/{project}/delivery-notes', [WarehouseController::class, 'deliveryNotes']);
    Route::post('/projects/{project}/delivery-notes', [WarehouseController::class, 'storeDeliveryNote']);
    Route::put('/projects/{project}/delivery-notes/{deliveryNote}', [WarehouseController::class, 'updateDeliveryNote']);

    Route::get('/projects/{project}/handover/milestones', [HandoverController::class, 'milestones']);
    Route::post('/projects/{project}/handover/milestones', [HandoverController::class, 'storeMilestone']);
    Route::get('/projects/{project}/handover/snags', [HandoverController::class, 'snags']);
    Route::post('/projects/{project}/handover/snags', [HandoverController::class, 'storeSnag']);
    Route::put('/projects/{project}/handover/snags/{snag}', [HandoverController::class, 'updateSnag']);
    Route::get('/projects/{project}/handover/checklist', [HandoverController::class, 'checklist']);
    Route::post('/projects/{project}/handover/checklist', [HandoverController::class, 'storeChecklistItem']);
    Route::put('/projects/{project}/handover/checklist/{checklistItem}', [HandoverController::class, 'updateChecklistItem']);
    Route::get('/projects/{project}/handover/sign-offs', [HandoverController::class, 'signOffs']);
    Route::post('/projects/{project}/handover/sign-offs', [HandoverController::class, 'storeSignOff']);
    Route::post('/projects/{project}/handover/complete', [HandoverController::class, 'markHandedOver']);
});
