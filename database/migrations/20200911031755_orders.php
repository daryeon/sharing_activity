<?php

use think\migration\Migrator;
use think\migration\db\Column;

class Orders extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $productProperty = $this->table('orders');
        $productProperty
            ->addColumn('tid', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('status', 'integer', ['limit' => 1, 'null' => false, 'default' => '0'])
            ->addColumn('type', 'integer', ['limit' => 1, 'null' => false])
            ->addColumn('order_type', 'integer', ['limit' => 3, 'null' => false, 'default' => '0'])
            ->addColumn('active_id', 'integer', ['limit' => 11, 'null' => false, 'default' => '0'])
            ->addColumn('total_fee', 'decimal', ['precision' => 10, 'scale' => 2, 'signed' => false, 'null' => false])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'signed' => false, 'null' => false])
            ->addColumn('pay_price', 'decimal', ['precision' => 10, 'scale' => 2, 'signed' => false, 'null' => false])
            ->addColumn('item_message', 'text', ['null' => false])
            ->addColumn('buyer_remark', 'string', ['limit' => 100, 'null' => true, 'default' => 'NULL'])
            ->addColumn('coupon_id', 'integer', ['limit' => 11, 'null' => true])
            ->addColumn('coupon_price', 'decimal', ['precision' => 10, 'scale' => 2, 'signed' => false, 'null' => false, 'default' => '0'])
            ->addColumn('num', 'integer', ['limit' => 1, 'null' => false])
            ->addColumn('item_id', 'integer', ['limit' => 11, 'null' => false])
            ->addColumn('sku_id', 'integer', ['limit' => 11, 'null' => false])
            ->addColumn('pay_type', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('pay_status', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('refund_state', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('refund_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addColumn('refund_message', 'text', ['null' => true])
            ->addColumn('pay_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addColumn('transaction_id', 'string', ['limit' => 100, 'null' => true, 'default' => NULL])
            ->addColumn('expired_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addColumn('update_time', 'integer', ['limit' => 10, 'null' => true, 'default' => '0', 'signed' => false])
            ->addColumn('create_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addIndex(['tid'])
            ->addIndex(['user_id'])
            ->addIndex(['item_id'])
            ->addIndex(['sku_id'])
            ->addIndex(['coupon_id'])
            ->create();
    }
}
