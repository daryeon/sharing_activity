<?php

use think\migration\Migrator;
use Phinx\Db\Adapter\MysqlAdapter;

class WxUser extends Migrator
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
        $thisModel = $this->table('wx_user');
        $thisModel
            ->addColumn('unionid', 'string', ['limit' => 100, 'default' => ''])
            ->addColumn('openid', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('mobile', 'string', ['limit' => 50, 'default' => ''])
            ->addColumn('avatar', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('user_type', 'integer', ['limit' => MysqlAdapter::INT_TINY, 'default' => '1'])
            ->addColumn('share_id', 'integer', ['default' => '0'])
            ->addColumn('last_visit_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addColumn('create_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addColumn('update_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addColumn('delete_time', 'integer', ['limit' => 10, 'default' => '0', 'signed' => false])
            ->addIndex(['openid'])
            ->addIndex(['unionid'])
            ->create();
    }
}
