<?php
namespace App\Controller\Stations;

use App\Entity;
use App\Exception\StationUnsupportedException;
use App\Form\StationStreamerForm;
use App\Http\Response;
use App\Http\ServerRequest;
use App\Service\AzuraCastCentral;
use App\Session\Flash;
use Psr\Http\Message\ResponseInterface;

class StreamersController extends AbstractStationCrudController
{
    protected AzuraCastCentral $ac_central;

    protected Entity\Repository\SettingsRepository $settingsRepo;

    public function __construct(
        StationStreamerForm $form,
        AzuraCastCentral $ac_central,
        Entity\Repository\SettingsRepository $settingsRepo
    ) {
        parent::__construct($form);

        $this->ac_central = $ac_central;
        $this->settingsRepo = $settingsRepo;
        $this->csrf_namespace = 'stations_streamers';
    }

    public function __invoke(ServerRequest $request, Response $response): ResponseInterface
    {
        $station = $request->getStation();
        $backend = $request->getStationBackend();

        if (!$backend::supportsStreamers()) {
            throw new StationUnsupportedException;
        }

        $view = $request->getView();

        if (!$station->getEnableStreamers()) {
            $params = $request->getQueryParams();
            if (isset($params['enable'])) {
                $station->setEnableStreamers(true);
                $this->em->persist($station);
                $this->em->flush();

                $request->getFlash()->addMessage('<b>' . __('Streamers enabled!') . '</b><br>' . __('You can now set up streamer (DJ) accounts.'),
                    Flash::SUCCESS);

                return $response->withRedirect($request->getRouter()->fromHere('stations:streamers:index'));
            }

            return $view->renderToResponse($response, 'stations/streamers/disabled');
        }

        $be_settings = (array)$station->getBackendConfig();

        return $view->renderToResponse($response, 'stations/streamers/index', [
            'server_url' => $this->settingsRepo->getSetting(Entity\Settings::BASE_URL, ''),
            'stream_port' => $backend->getStreamPort($station),
            'ip' => $this->ac_central->getIp(),
            'dj_mount_point' => $be_settings['dj_mount_point'] ?? '/',
            'station_tz' => $station->getTimezone(),
        ]);
    }
}
