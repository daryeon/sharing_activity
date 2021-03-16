<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddColumnApiTokenToWxUser extends Migrator
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
        $table = $this->table('wx_user');
        $table
            ->addColumn('api_token', 'string', ['limit' => 100, 'null' => false])
            ->addIndex(['api_token'], [
                'unique' => true,
                'name' => 'wx_user_api_token'])
            ->update();
    }
}
