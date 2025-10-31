<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;
use Tourze\TusUploadServerBundle\Entity\Upload;

class UploadFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $upload1 = new Upload();
        $upload1->setUploadId(Uuid::uuid4()->toString());
        $upload1->setFilename('example1.jpg');
        $upload1->setMimeType('image/jpeg');
        $upload1->setSize(1024 * 1024);
        $upload1->setOffset(0);
        $upload1->setMetadata(['filename' => 'example1.jpg', 'filetype' => 'image/jpeg']);
        $upload1->setFilePath('/tmp/uploads/example1.jpg');
        $upload1->setCompleted(false);
        $upload1->setExpiredTime(new \DateTimeImmutable('+7 days'));

        $upload2 = new Upload();
        $upload2->setUploadId(Uuid::uuid4()->toString());
        $upload2->setFilename('example2.pdf');
        $upload2->setMimeType('application/pdf');
        $upload2->setSize(2 * 1024 * 1024);
        $upload2->setOffset(2 * 1024 * 1024);
        $upload2->setMetadata(['filename' => 'example2.pdf', 'filetype' => 'application/pdf']);
        $upload2->setFilePath('/tmp/uploads/example2.pdf');
        $upload2->setCompleted(true);
        $upload2->setExpiredTime(new \DateTimeImmutable('+7 days'));

        $upload3 = new Upload();
        $upload3->setUploadId(Uuid::uuid4()->toString());
        $upload3->setFilename('example3.mp4');
        $upload3->setMimeType('video/mp4');
        $upload3->setSize(10 * 1024 * 1024);
        $upload3->setOffset(5 * 1024 * 1024);
        $upload3->setMetadata(['filename' => 'example3.mp4', 'filetype' => 'video/mp4']);
        $upload3->setFilePath('/tmp/uploads/example3.mp4');
        $upload3->setCompleted(false);
        $upload3->setExpiredTime(new \DateTimeImmutable('+7 days'));

        $manager->persist($upload1);
        $manager->persist($upload2);
        $manager->persist($upload3);

        $manager->flush();
    }
}
