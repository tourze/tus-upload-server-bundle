<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Service;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\TusUploadServerBundle\Controller\Admin\UploadCrudController;
use Tourze\TusUploadServerBundle\Entity\Upload;

#[Autoconfigure(public: true)]
final readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('TUS上传管理')) {
            $item->addChild('TUS上传管理')
                ->setAttribute('icon', 'fas fa-cloud-upload-alt')
            ;
        }

        $tusMenu = $item->getChild('TUS上传管理');
        if (null === $tusMenu) {
            return;
        }

        $tusMenu->addChild('TUS上传记录')
            ->setUri($this->linkGenerator->getCurdListPage(Upload::class))
            ->setAttribute('icon', 'fas fa-file-upload')
        ;
    }

    /** @return iterable<MenuItemInterface> */
    public function getMenuItems(): iterable
    {
        yield MenuItem::section('TUS上传管理', 'fas fa-cloud-upload-alt');
        yield MenuItem::linkToCrud('TUS上传记录', 'fas fa-file-upload', UploadCrudController::class);
    }
}
