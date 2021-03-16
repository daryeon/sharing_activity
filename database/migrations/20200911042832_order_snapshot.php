<?php

use think\migration\Migrator;
use think\migration\db\Column;

class OrderSnapshot extends Migrator
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
        $productProperty = $this->table('order_snapshot');
        $productProperty
            ->addColumn('tid', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('user_id', 'integer', ['limit' => 11, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('type', 'integer', ['limit' => 3, 'null' => false, 'default' => '0'])
            ->addColumn('good_price', 'decimal', ['precision' => 10, 'scale' => 2, 'signed' => false, 'null' => false]) // 商品价
            ->addColumn('coupon_value', 'decimal', ['precision' => 10, 'scale' => 2, 'signed' => false, 'null' => false])
            ->addColumn('manbipei', 'integer', ['limit' => 3, 'null' => false, 'default' => 0])
            ->addColumn('require_info_type', 'integer', ['limit' => 3, 'null' => false, 'default' => 0])
            ->addColumn('product_id', 'integer', ['limit' => 11, 'null' => false])
            ->addColumn('goods_id', 'integer', ['limit' => 11, 'null' => false])
            ->addColumn('property_value_path', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('property_value_path_text', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'signed' => false, 'null' => false]) // 售价（用券前）
            ->addColumn('org_price', 'decimal', ['precision' => 10, 'scale' => 2, 'signed' => false, 'null' => false]) // 原价
            ->addColumn('pay_price', 'decimal', ['precision' => 10, 'scale' => 2, 'signed' => false, 'null' => false])
            ->addColumn('item_message', 'text', ['null' => true])
            ->addColumn('update_time', 'integer', ['limit' => 10, 'null' => true, 'default' => '0', 'signed' => false])
            ->addColumn('create_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addIndex(['tid'])
            ->addIndex(['user_id'])
            ->create();
    }
}
