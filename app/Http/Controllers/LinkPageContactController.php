<?php

namespace App\Http\Controllers;

use App\Actions\LinkPage\BuildVcard;
use App\Enums\LinkPage;
use App\Presenters\LinkPagePresenter;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

class LinkPageContactController extends Controller
{
    public function __invoke(LinkPage $page, LinkPagePresenter $presenter, BuildVcard $buildVcard): Response
    {
        $card = $presenter->contact($page);

        return response($buildVcard($card), headers: [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $card->filename()),
        ]);
    }
}
