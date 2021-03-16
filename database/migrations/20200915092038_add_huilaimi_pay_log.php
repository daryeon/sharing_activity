<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddHuilaimiPayLog extends Migrator
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
        $productProperty = $this->table('pay_log');
        $productProperty
            ->addColumn('pay_id', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('tid', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('status', 'integer', ['limit' => 1, 'null' => false, 'default' => 0])
            ->addColumn('order_type', 'string', ['limit' => 10, 'null' => false, 'default' => NULL])
            ->addColumn('attach', 'text', ['null' => false])
            ->addColumn('total_fee', 'decimal', ['precision' => 10, 'scale' => 2, 'signed' => false, 'null' => false])
            ->addColumn('update_time', 'integer', ['limit' => 10, 'null' => true, 'default' => '0', 'signed' => false])
            ->addColumn('create_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addIndex(['tid'])
            ->create();
    }
}
