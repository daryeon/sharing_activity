<?php

use think\migration\Migrator;
use think\migration\db\Column;

class OrderLogs extends Migrator
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
        $productProperty = $this->table('order_log');
        $productProperty
            ->addColumn('tid', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('old_order', 'text', ['null' => true])
            ->addColumn('new_order', 'text', ['null' => false])
            ->addColumn('act', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('operate', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('update_time', 'integer', ['limit' => 10, 'null' => true, 'default' => '0', 'signed' => false])
            ->addColumn('create_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addIndex(['tid'])
            ->create();
    }
}
