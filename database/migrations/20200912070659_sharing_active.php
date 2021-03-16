<?php

use think\migration\Migrator;
use think\migration\db\Column;

class SharingActive extends Migrator
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
        // 拼团拼单记录表
//        $productProperty = $this->table('sharing_active', ['id' => false, 'primary_key' => ['active_id']]);
        $productProperty = $this->table('sharing_active');
        $productProperty
//            ->addColumn('active_id', 'integer', ['limit' => 11, 'null' => false])
            ->addColumn('goods_id', 'integer', ['limit' => 11, 'default' => '0', 'null' => false])
            ->addColumn('people', 'integer', ['limit' => 11, 'default' => '0', 'null' => false])
            ->addColumn('actual_people', 'integer', ['limit' => 11, 'default' => '0', 'null' => false])
            ->addColumn('creator_id', 'integer', ['limit' => 11, 'default' => '0', 'null' => false])
            ->addColumn('end_time', 'integer', ['limit' => 10, 'default' => '0', 'null' => false])
            ->addColumn('status', 'integer', ['limit' => 3, 'default' => '0', 'null' => false])
            ->addColumn('create_time', 'integer', ['limit' => 10, 'default' => '0', 'null' => false])
            ->addColumn('update_time', 'integer', ['limit' => 10, 'default' => '0', 'null' => false])
            ->addIndex('goods_id')
            ->create();
    }
}
