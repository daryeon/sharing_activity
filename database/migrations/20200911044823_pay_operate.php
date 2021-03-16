<?php

use think\migration\Migrator;
use think\migration\db\Column;

class PayOperate extends Migrator
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
        $productProperty = $this->table('pay_operate');
        $productProperty
            ->addColumn('user_id', 'integer', ['limit' => 11, 'null' => false])
            ->addColumn('operator', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('act', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('data', 'text', ['null' => true])
            ->addColumn('update_time', 'integer', ['limit' => 10, 'null' => true, 'default' => '0', 'signed' => false])
            ->addColumn('create_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addIndex('user_id')
            ->create();
    }
}
