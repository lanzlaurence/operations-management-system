<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardening pass over the audit trail and the document tables.
 *
 *  - transaction_logs keeps the acting user optional (console and seeded
 *    actions have none) and records the request IP.
 *  - both log tables and the document tables get the indexes the activity
 *    screens, the dashboard and the status recalculations actually filter on.
 *  - the entity log tables gain a `restored` action to match EntityLogAction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_logs', function (Blueprint $table): void {
            $table->string('ip_address', 45)->nullable()->after('remarks');
        });

        // The acting user is unknown for seeded and console-driven actions.
        Schema::table('transaction_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('inventory_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('transaction_logs', function (Blueprint $table): void {
            $table->index(['loggable_type', 'loggable_id', 'created_at'], 'transaction_logs_document_index');
            $table->index('action', 'transaction_logs_action_index');
            $table->index('created_at', 'transaction_logs_created_at_index');
        });

        Schema::table('inventory_logs', function (Blueprint $table): void {
            $table->index(['material_id', 'location_id', 'created_at'], 'inventory_logs_stock_index');
            $table->index('type', 'inventory_logs_type_index');
            $table->index(['reference_type', 'reference_id'], 'inventory_logs_reference_index');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            // vendor_id is already indexed by its foreign key.
            $table->index(['status', 'order_date'], 'purchase_orders_status_date_index');
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            // customer_id is already indexed by its foreign key.
            $table->index(['status', 'order_date'], 'sales_orders_status_date_index');
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->index(['purchase_order_id', 'status'], 'goods_receipts_order_status_index');
        });

        Schema::table('goods_issues', function (Blueprint $table): void {
            $table->index(['sales_order_id', 'status'], 'goods_issues_order_status_index');
        });

        Schema::table('inventories', function (Blueprint $table): void {
            $table->index(['material_id', 'location_id'], 'inventories_material_location_index');
        });

        foreach (['material_logs', 'vendor_logs', 'customer_logs'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->enum('action', ['created', 'updated', 'deleted', 'restored'])->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('transaction_logs', function (Blueprint $table): void {
            $table->dropIndex('transaction_logs_document_index');
            $table->dropIndex('transaction_logs_action_index');
            $table->dropIndex('transaction_logs_created_at_index');
            $table->dropColumn('ip_address');
        });

        Schema::table('inventory_logs', function (Blueprint $table): void {
            $table->dropIndex('inventory_logs_stock_index');
            $table->dropIndex('inventory_logs_type_index');
            $table->dropIndex('inventory_logs_reference_index');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex('purchase_orders_status_date_index');
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropIndex('sales_orders_status_date_index');
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropIndex('goods_receipts_order_status_index');
        });

        Schema::table('goods_issues', function (Blueprint $table): void {
            $table->dropIndex('goods_issues_order_status_index');
        });

        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropIndex('inventories_material_location_index');
        });

        foreach (['material_logs', 'vendor_logs', 'customer_logs'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->enum('action', ['created', 'updated', 'deleted'])->change();
            });
        }
    }
};
