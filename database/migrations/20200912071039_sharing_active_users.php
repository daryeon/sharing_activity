<?php

use think\migration\Migrator;
use think\migration\db\Column;

class SharingActiveUsers extends Migrator
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
        // 拼团拼单成员记录表
        $productProperty = $this->table('sharing_active_users');
        $productProperty
            ->addColumn('active_id', 'integer', ['limit' => 11, 'default' => '0', 'null' => false])
            ->addColumn('tid', 'string', ['limit' => 30, 'default' => 'NULL', 'null' => false])
            ->addColumn('user_id', 'integer', ['limit' => 11, 'default' => '0', 'null' => false])
            ->addColumn('is_creator', 'integer', ['limit' => 3, 'default' => '0', 'null' => false])
            ->addColumn('create_time', 'integer', ['limit' => 10, 'default' => '0', 'null' => false])
            ->addIndex('active_id')
            ->addIndex('tid')
            ->addIndex('user_id')
            ->create();
    }
}
