<?php

use App\Http\Controllers\BillboardController;
use App\Http\Controllers\SelectOptionController;
use Inertia\Inertia;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\RebateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StructureController;
use App\Http\Controllers\AssetMasterController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DownloadCenterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TradingAccountController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\TradePositionController;

Route::get('locale/{locale}', function ($locale) {
    App::setLocale($locale);
    Session::put("locale", $locale);

    return redirect()->back();
});

Route::get('/', function () {
    return redirect(route('login'));
});

Route::get('/admin_login/{hashedToken}', [DashboardController::class, 'admin_login']);
Route::post('deposit_callback', [TradingAccountController::class, 'depositCallback'])->name('depositCallback');
Route::post('hypay_deposit_callback', [TradingAccountController::class, 'hypay_deposit_callback'])->name('hypay_deposit_callback');
Route::post('psp_deposit_callback', [TradingAccountController::class, 'psp_deposit_callback'])->name('psp_deposit_callback');
Route::post('zpay_deposit_callback', [TradingAccountController::class, 'zpay_deposit_callback'])->name('zpay_deposit_callback');

Route::get('/confirmWithdrawal/{transaction_number}/{token}', [TransactionController::class, 'confirmWithdrawal'])->name('confirmWithdrawal');

Route::middleware(['auth','verified'])->group(function () {
    Route::get('deposit_return', [TradingAccountController::class, 'depositReturn'])->name('depositReturn');

    Route::get('/getUserMarkupProfiles', [GeneralController::class, 'getUserMarkupProfiles'])->name('getUserMarkupProfiles');
    // Select Option
    Route::get('getPaymentMethodRule', [SelectOptionController::class, 'getPaymentMethodRule'])->name('getPaymentMethodRule');
    Route::get('getPaymentGateways', [SelectOptionController::class, 'getPaymentGateways'])->name('getPaymentGateways');
    Route::get('getWithdrawalPaymentAccounts', [SelectOptionController::class, 'getWithdrawalPaymentAccounts'])->name('getWithdrawalPaymentAccounts');
    Route::get('getWithdrawalCondition', [SelectOptionController::class, 'getWithdrawalCondition'])->name('getWithdrawalCondition');

    /**
     * ==============================
     *          Dashboard
     * ==============================
     */
    Route::prefix('dashboard')->group(function() {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/getDashboardData', [DashboardController::class, 'getDashboardData'])->name('getDashboardData');
        Route::get('/getRebateEarnData', [DashboardController::class, 'getRebateEarnData'])->name('getRebateEarnData');
        Route::get('/getPosts', [DashboardController::class, 'getPosts'])->name('member.getPosts');

        Route::post('/applyRebate', [TransactionController::class, 'applyRebate'])->name('dashboard.applyRebate');
        Route::post('/walletTransfer', [TransactionController::class, 'walletTransfer'])->name('dashboard.walletTransfer');
        Route::post('/walletWithdrawal', [TransactionController::class, 'walletWithdrawal'])->name('dashboard.walletWithdrawal');
        Route::post('/createPost', [DashboardController::class, 'createPost'])->name('dashboard.createPost');
    });

    /**
     * ==============================
     *         Structure
     * ==============================
     */
    Route::prefix('structure')->group(function() {
        Route::get('/', [StructureController::class, 'show'])->name('structure');
        Route::get('/getDownlineData', [StructureController::class, 'getDownlineData'])->name('structure.getDownlineData');
        Route::get('/getDownlineListingData', [StructureController::class, 'getDownlineListingData'])->name('structure.getDownlineListingData');
        Route::get('/getFilterData', [StructureController::class, 'getFilterData'])->name('structure.getFilterData');
        Route::get('/downline/{id_number}', [StructureController::class, 'viewDownline'])->name('structure.viewDownline');
        Route::get('/getUserData', [StructureController::class, 'getUserData'])->name('structure.getUserData');
    });

    /**
     * ==============================
     *            Account
     * ==============================
     */
    Route::prefix('account')->group(function () {
        Route::get('/', [TradingAccountController::class, 'index'])->name('account');
        Route::get('/getOptions', [TradingAccountController::class, 'getOptions'])->name('account.getOptions');
        Route::get('/getAccountReport', [TradingAccountController::class, 'getAccountReport'])->name('account.getAccountReport');
        Route::get('/getLiveAccount', [TradingAccountController::class, 'getLiveAccount'])->name('account.getLiveAccount');
        Route::post('/create_live_account', [TradingAccountController::class, 'create_live_account'])->name('account.create_live_account');
        Route::post('/create_demo_account', [TradingAccountController::class, 'create_demo_account'])->name('account.create_demo_account');
        Route::post('/deposit_to_account', [TradingAccountController::class, 'deposit_to_account'])->name('account.deposit_to_account');
        Route::post('/withdrawal_from_account', [TradingAccountController::class, 'withdrawal_from_account'])->name('account.withdrawal_from_account');
        Route::post('/change_leverage', [TradingAccountController::class, 'change_leverage'])->name('account.change_leverage');
        Route::post('/change_password', [TradingAccountController::class, 'change_password'])->name('account.change_password');
        Route::post('/internal_transfer', [TradingAccountController::class, 'internal_transfer'])->name('account.internal_transfer');
        Route::post('/revoke_account', [TradingAccountController::class, 'revoke_account'])->name('account.revoke_account');
        Route::delete('/delete_account', [TradingAccountController::class, 'delete_account'])->name('account.delete_account');
    });

    /**
     * ==============================
     *          Asset Master
     * ==============================
     */
    // Route::prefix('asset_master')->group(function () {
    //     Route::get('/', [AssetMasterController::class, 'index'])->name('asset_master');
    //     Route::get('/getMasters', [AssetMasterController::class, 'getMasters'])->name('asset_master.getMasters');
    //     Route::get('/getAvailableAccounts', [AssetMasterController::class, 'getAvailableAccounts'])->name('asset_master.getAvailableAccounts');
    //     Route::get('/info/{id}', [AssetMasterController::class, 'showPammInfo'])->name('asset_master.showPammInfo');
    //     Route::get('/getMasterDetail', [AssetMasterController::class, 'getMasterDetail'])->name('asset_master.getMasterDetail');
    //     Route::get('/getMasterMonthlyProfit', [AssetMasterController::class, 'getMasterMonthlyProfit'])->name('asset_master.getMasterMonthlyProfit');

    //     Route::post('joinPamm', [AssetMasterController::class, 'joinPamm'])->name('asset_master.joinPamm');
    //     Route::post('addToFavourites', [AssetMasterController::class, 'addToFavourites'])->name('asset_master.addToFavourites');
    // });

    /**
     * ==============================
     *          Report
     * ==============================
     */
    Route::prefix('report')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('report');
        Route::get('/getRebateSummary', [ReportController::class, 'getRebateSummary'])->name('report.getRebateSummary');
        Route::get('/getRebateListing', [ReportController::class, 'getRebateListing'])->name('report.getRebateListing');
        Route::get('/getGroupTransaction', [ReportController::class, 'getGroupTransaction'])->name('report.getGroupTransaction');
        Route::get('/getRebateHistory', [ReportController::class, 'getRebateHistory'])->name('report.getRebateHistory');
    });

    /**
     * ==============================
     *        Rebate Allocate
     * ==============================
     */
    Route::prefix('rebate_allocate')->group(function () {
        Route::get('/', [RebateController::class, 'index'])->name('rebate_allocate');
        Route::get('/getRebateAllocateData', [RebateController::class, 'getRebateAllocateData'])->name('rebate_allocate.getRebateAllocateData');
        Route::get('/getAgents', [RebateController::class, 'getAgents'])->name('rebate_allocate.getAgents');
        Route::get('/changeAgents', [RebateController::class, 'changeAgents'])->name('rebate_allocate.changeAgents');

        Route::post('/updateRebateAmount', [RebateController::class, 'updateRebateAmount'])->name('rebate_allocate.updateRebateAmount');
    });

    /**
     * ==============================
     *          Billboard
     * ==============================
     */
    Route::prefix('billboard')->group(function () {
        Route::get('/', [BillboardController::class, 'index'])->name('billboard');
        Route::get('/getBonusWallet', [BillboardController::class, 'getBonusWallet'])->name('billboard.getBonusWallet');
        Route::get('/getTargetAchievements', [BillboardController::class, 'getTargetAchievements'])->name('billboard.getTargetAchievements');
        Route::get('/getTotalEarnedBonusData', [BillboardController::class, 'getTotalEarnedBonusData'])->name('billboard.getTotalEarnedBonusData');
        Route::get('/getStatementData', [BillboardController::class, 'getStatementData'])->name('billboard.getStatementData');
        Route::get('/getBonusWithdrawalHistories', [BillboardController::class, 'getBonusWithdrawalHistories'])->name('billboard.getBonusWithdrawalHistories');
    });

    /**
     * ==============================
     *          Transaction
     * ==============================
     */
    Route::prefix('transaction')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('transaction');
        Route::get('/getTotal', [TransactionController::class, 'getTotal'])->name('transaction.getTotal');
        Route::get('/getTransactions', [TransactionController::class, 'getTransactions'])->name('transaction.getTransactions');
        Route::get('/getRebateTransactions', [TransactionController::class, 'getRebateTransactions'])->name('transaction.getRebateTransactions');
        Route::post('/cancelWithdrawal', [TransactionController::class, 'cancelWithdrawal'])->name('transaction.cancel_withdrawal');
    });

    /**
     * ==============================
     *         Trade Position
     * ==============================
     */
    Route::prefix('trade_positions')->group(function () {
        Route::get('/open_positions', [TradePositionController::class, 'open_positions'])->name('trade_positions.open_positions');
        Route::get('/open_trade', [TradePositionController::class, 'open_trade'])->name('trade_positions.open_trade');
        Route::get('/closed_positions', [TradePositionController::class, 'closed_positions'])->name('trade_positions.closed_positions');
        Route::get('/closed_trade', [TradePositionController::class, 'closed_trade'])->name('trade_positions.closed_trade');
    });

    /**
     * ==============================
     *        Download Center
     * ==============================
     */
    Route::prefix('download_center')->group(function () {
        Route::get('/', [DownloadCenterController::class, 'index'])->name('download_center');
    });

    /**
     * ==============================
     *            Profile
     * ==============================
     */
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('profile');
        Route::get('/getFilterData', [ProfileController::class, 'getFilterData'])->name('profile.getFilterData');
        Route::get('/getKycVerification', [ProfileController::class, 'getKycVerification'])->name('profile.getKycVerification');
        Route::get('/getPaymentAccounts', [ProfileController::class, 'getPaymentAccounts'])->name('profile.getPaymentAccounts');

        Route::post('/addPaymentAccount', [ProfileController::class, 'addPaymentAccount'])->name('profile.addPaymentAccount');
        Route::post('/updatePaymentAccount', [ProfileController::class, 'updatePaymentAccount'])->name('profile.updatePaymentAccount');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/updateProfilePhoto', [ProfileController::class, 'updateProfilePhoto'])->name('profile.updateProfilePhoto');
        Route::post('/updateKyc', [ProfileController::class, 'updateKyc'])->name('profile.updateKyc');
        Route::post('/updateCryptoWalletInfo', [ProfileController::class, 'updateCryptoWalletInfo'])->name('profile.updateCryptoWalletInfo');
        Route::post('/updateBankInfo', [ProfileController::class, 'updateBankInfo'])->name('profile.updateBankInfo');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

require __DIR__.'/auth.php';
