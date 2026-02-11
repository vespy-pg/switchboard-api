<?php

namespace App\DTO;

use Symfony\Component\Serializer\Annotation\Groups;

class LinkPreview
{
    #[Groups(['gift_list_item_link_preview_json'])]
    private ?string $url = null;

    #[Groups(['gift_list_item_link_preview_json'])]
    private ?string $title = null;

    #[Groups(['gift_list_item_link_preview_json'])]
    private ?string $status = null;

    #[Groups(['gift_list_item_link_preview_json'])]
    private ?string $product = null;

    #[Groups(['gift_list_item_link_preview_json'])]
    private ?string $finalUrl = null;

    #[Groups(['gift_list_item_link_preview_json'])]
    private ?string $imageUrl = null;

    #[Groups(['gift_list_item_link_preview_json'])]
    private ?string $siteName = null;

    #[Groups(['gift_list_item_link_preview_json'])]
    private ?string $faviconUrl = null;

    #[Groups(['gift_list_item_link_preview_json'])]
    private ?string $description = null;

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getProduct(): ?string
    {
        return $this->product;
    }

    public function setProduct(?string $product): void
    {
        $this->product = $product;
    }

    public function getFinalUrl(): ?string
    {
        return $this->finalUrl;
    }

    public function setFinalUrl(?string $finalUrl): void
    {
        $this->finalUrl = $finalUrl;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): void
    {
        $this->imageUrl = $imageUrl;
    }

    public function getSiteName(): ?string
    {
        return $this->siteName;
    }

    public function setSiteName(?string $siteName): void
    {
        $this->siteName = $siteName;
    }

    public function getFaviconUrl(): ?string
    {
        return $this->faviconUrl;
    }

    public function setFaviconUrl(?string $faviconUrl): void
    {
        $this->faviconUrl = $faviconUrl;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
}
