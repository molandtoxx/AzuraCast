<?php
namespace App\Controller\Admin;

use App\Config;
use App\Entity\Repository\SettingsRepository;
use App\Entity\Settings;
use App\Exception\NotFoundException;
use App\Flysystem\FilesystemGroup;
use App\Form\BackupSettingsForm;
use App\Form\Form;
use App\Http\Response;
use App\Http\ServerRequest;
use App\Session\Flash;
use App\Sync\Task\Backup;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Psr\Http\Message\ResponseInterface;

class BackupsController
{
    protected SettingsRepository $settingsRepo;

    protected Backup $backupTask;

    protected Filesystem $backupFs;

    protected string $csrfNamespace = 'admin_backups';

    public function __construct(
        SettingsRepository $settings_repo,
        Backup $backup_task
    ) {
        $this->settingsRepo = $settings_repo;
        $this->backupTask = $backup_task;
        $this->backupFs = new Filesystem(new Local(Backup::BASE_DIR));
    }

    public function __invoke(ServerRequest $request, Response $response): ResponseInterface
    {
        return $request->getView()->renderToResponse($response, 'admin/backups/index', [
            'backups' => $this->backupFs->listContents('', false),
            'is_enabled' => (bool)$this->settingsRepo->getSetting(Settings::BACKUP_ENABLED, false),
            'last_run' => $this->settingsRepo->getSetting(Settings::BACKUP_LAST_RUN, 0),
            'last_result' => $this->settingsRepo->getSetting(Settings::BACKUP_LAST_RESULT, 0),
            'last_output' => $this->settingsRepo->getSetting(Settings::BACKUP_LAST_OUTPUT, ''),
            'csrf' => $request->getCsrf()->generate($this->csrfNamespace),
        ]);
    }

    public function configureAction(
        ServerRequest $request,
        Response $response,
        BackupSettingsForm $settingsForm
    ): ResponseInterface {
        if (false !== $settingsForm->process($request)) {
            $request->getFlash()->addMessage(__('Changes saved.'), Flash::SUCCESS);
            return $response->withRedirect($request->getRouter()->fromHere('admin:backups:index'));
        }

        return $request->getView()->renderToResponse($response, 'system/form_page', [
            'form' => $settingsForm,
            'render_mode' => 'edit',
            'title' => __('Configure Backups'),
        ]);
    }

    public function runAction(
        ServerRequest $request,
        Response $response,
        Config $config
    ): ResponseInterface {
        $runForm = new Form($config->get('forms/backup_run'));

        // Handle submission.
        if ($request->isPost() && $runForm->isValid($request->getParsedBody())) {
            $data = $runForm->getValues();

            [$result_code, $result_output] = $this->backupTask->runBackup($data['path'], $data['exclude_media']);

            $is_successful = (0 === $result_code);

            return $request->getView()->renderToResponse($response, 'admin/backups/run', [
                'title' => __('Run Manual Backup'),
                'path' => $data['path'],
                'is_successful' => $is_successful,
                'output' => $result_output,
            ]);
        }

        return $request->getView()->renderToResponse($response, 'system/form_page', [
            'form' => $runForm,
            'render_mode' => 'edit',
            'title' => __('Run Manual Backup'),
        ]);
    }

    public function downloadAction(
        ServerRequest $request,
        Response $response,
        $path
    ): ResponseInterface {
        $path = $this->getFilePath($path);
        $path = 'backup://' . $path;

        $fsGroup = new FilesystemGroup([
            'backup' => $this->backupFs,
        ]);

        return $response->withNoCache()
            ->withFlysystemFile($fsGroup, $path);
    }

    public function deleteAction(ServerRequest $request, Response $response, $path, $csrf): ResponseInterface
    {
        $request->getCsrf()->verify($csrf, $this->csrfNamespace);

        $path = $this->getFilePath($path);
        $this->backupFs->delete($path);

        $request->getFlash()->addMessage('<b>' . __('Backup deleted.') . '</b>', Flash::SUCCESS);
        return $response->withRedirect($request->getRouter()->named('admin:backups:index'));
    }

    protected function getFilePath($raw_path): string
    {
        $path = basename(base64_decode($raw_path));

        if (!$this->backupFs->has($path)) {
            throw new NotFoundException(__('Backup not found.'));
        }

        return $path;
    }
}
