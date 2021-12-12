<?php
declare(strict_types=1);

namespace Utilities\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Schema\TableSchema;
use Cake\Utility\Security;

/**
 * RecreateAdmin command.
 *
 * トランケートして、id=1の管理者を更新する
 * 一般ユーザーがいたら新しいパスワードを再設定して保存しなおす
 *
 * .envのSECURITY_SALT不一致なんかでログインできなくなったときに使うこと
 *
 * @property \App\Model\Table\AdminsTable $Admins
 */
class RecreateAdminCommand extends Command
{
    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/4/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->addArgument('mail', [
            'required' => true,
            'help' => 'Please input mail address.',
        ]);
        $parser->addArgument('password', [
            'required' => true,
            'help' => 'Please input password.',
        ]);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return null|void|int The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->Admins = $this->fetchTable('Admins');

        // 一般ユーザー取得
        $normal_admins = $this->Admins->find()->where([$this->Admins->aliasField('id <>') => 1])->toArray();

        // トランケート
        $sqls = (new TableSchema($this->Admins->getTable()))->truncateSql($this->Admins->getConnection());
        foreach ($sqls as $sql) {
            $this->Admins->getConnection()->execute($sql)->execute();
        }

        // 管理者再生成
        $mail = $args->getArgument('mail');
        $password = $args->getArgument('password');
        $admin = $this->Admins->newEntity([
            'name' => '管理者',
            'mail' => $mail,
            'password' => $password,
            'use_otp' => '0',
            'otp_secret' => null,
        ]);
        $this->Admins->save($admin);
        $io->out('以下の情報で管理者を再生成しました。');
        $output_data = [
            ['admin id', 'mail', 'new password'],
            ['1', $mail, $password]
        ];
        $io->helper('Table')->output($output_data);

        // 一般ユーザーがいたらランダムな16桁のパスワードを設定して更新
        if (!is_null($normal_admins) && count($normal_admins) > 0) {
            unset($output_data[1]);
            foreach ($normal_admins as $normal_admin) {
                $new_password = Security::randomString(16);
                $normal_admin = $this->Admins->patchEntity($normal_admin, [
                    'name' => '',
                    'mail' => $normal_admin->mail,
                    'password' => $new_password,
                    'use_otp' => '0',
                    'otp_secret' => null,
                ])->setNew(true);
                $this->Admins->save($normal_admin);
                $output_data[] = [(string)$normal_admin->id, $normal_admin->mail, $new_password];
            }
            $io->out('一般ユーザーはそれぞれ以下ように更新しました。必要に応じて通知を行ってください。');
            $io->helper('Table')->output($output_data);
        }
    }
}
