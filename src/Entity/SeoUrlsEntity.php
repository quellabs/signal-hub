<?php
    namespace Services\Entity;

    /**
     * Class SeoUrlsEntity
     * @package Services\Entity
     * @Orm\Table(name="seo_urls")
     */
    class SeoUrlsEntity {
        /**
         * @Orm\Column(name="id", type="int", length=11, primary_key=true, auto_increment=true)
         */
        private int $id;

        /**
         * @Orm\Column(name="url", type="text")
         */
        private string $url;

        /**
         * @Orm\Column(name="hash", type="varchar", length=16, nullable=true)
         */
        private ?string $hash;

        /**
         * @Orm\Column(name="language_id", type="smallint", length=6, nullable=true)
         */
        private ?int $languageId;

        /**
         * @Orm\Column(name="page_id", type="int", length=11, nullable=true)
         */
        private ?int $pageId;

        /**
         * @Orm\Column(name="page_type", type="smallint", length=6, nullable=true)
         */
        private ?int $pageType;

        /**
         * @Orm\Column(name="controller", type="varchar", length=64, nullable=true)
         */
        private ?string $controller;

        /**
         * @Orm\Column(name="status", type="tinyint", length=4, nullable=true)
         */
        private ?int $status;

        /**
         * @Orm\Column(name="cPath", type="varchar", length=128, nullable=true)
         */
        private ?string $cPath;

        /**
         * @Orm\Column(name="redirect", type="varchar", length=255, nullable=true)
         */
        private ?string $redirect;

        /**
         * @Orm\Column(name="meta_title", type="varchar", length=128, nullable=true)
         */
        private ?string $metaTitle;

        /**
         * @Orm\Column(name="meta_description", type="varchar", length=128, nullable=true)
         */
        private ?string $metaDescription;

        /**
         * @Orm\Column(name="date_created", type="datetime", nullable=true)
         */
        private ?\DateTime $dateCreated;

        /**
         * @Orm\Column(name="last_modified", type="datetime", nullable=true)
         */
        private ?\DateTime $lastModified;

        /**
         * @Orm\Column(name="middleware", type="text", nullable=true)
         */
        private ?string $middleware;

        /**
         * @Orm\Column(name="has_parameters", type="tinyint", length=4)
         */
        private int $hasParameters;
		
		public function getId() : int {
            return $this->id;
        }


        public function getUrl() : string {
            return $this->url;
        }

        public function setUrl(string $value): SeoUrlsEntity {
            $this->url = $value;
            return $this;
        }

        public function getHash() : ?string {
            return $this->hash;
        }

        public function setHash(?string $value): SeoUrlsEntity {
            $this->hash = $value;
            return $this;
        }

        public function getLanguageId() : ?int {
            return $this->languageId;
        }

        public function setLanguageId(?int $value): SeoUrlsEntity {
            $this->languageId = $value;
            return $this;
        }

        public function getPageId() : ?int {
            return $this->pageId;
        }

        public function setPageId(?int $value): SeoUrlsEntity {
            $this->pageId = $value;
            return $this;
        }

        public function getPageType() : ?int {
            return $this->pageType;
        }

        public function setPageType(?int $value): SeoUrlsEntity {
            $this->pageType = $value;
            return $this;
        }

        public function getController() : ?string {
            return $this->controller;
        }

        public function setController(?string $value): SeoUrlsEntity {
            $this->controller = $value;
            return $this;
        }

        public function getStatus() : ?int {
            return $this->status;
        }

        public function setStatus(?int $value): SeoUrlsEntity {
            $this->status = $value;
            return $this;
        }

        public function getCPath() : ?string {
            return $this->cPath;
        }

        public function setCPath(?string $value): SeoUrlsEntity {
            $this->cPath = $value;
            return $this;
        }

        public function getRedirect() : ?string {
            return $this->redirect;
        }

        public function setRedirect(?string $value): SeoUrlsEntity {
            $this->redirect = $value;
            return $this;
        }

        public function getMetaTitle() : ?string {
            return $this->metaTitle;
        }

        public function setMetaTitle(?string $value): SeoUrlsEntity {
            $this->metaTitle = $value;
            return $this;
        }

        public function getMetaDescription() : ?string {
            return $this->metaDescription;
        }

        public function setMetaDescription(?string $value): SeoUrlsEntity {
            $this->metaDescription = $value;
            return $this;
        }

        public function getDateCreated() : ?\DateTime {
            return $this->dateCreated;
        }

        public function setDateCreated(?\DateTime $value): SeoUrlsEntity {
            $this->dateCreated = $value;
            return $this;
        }

        public function getLastModified() : ?\DateTime {
            return $this->lastModified;
        }

        public function setLastModified(?\DateTime $value): SeoUrlsEntity {
            $this->lastModified = $value;
            return $this;
        }

        public function getMiddleware() : ?string {
            return $this->middleware;
        }

        public function setMiddleware(?string $value): SeoUrlsEntity {
            $this->middleware = $value;
            return $this;
        }

        public function getHasParameters() : int {
            return $this->hasParameters;
        }

        public function setHasParameters(int $value): SeoUrlsEntity {
            $this->hasParameters = $value;
            return $this;
        }
    }