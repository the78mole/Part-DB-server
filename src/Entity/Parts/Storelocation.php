<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Entity\Parts;

use App\Entity\Attachments\Attachment;
use App\Repository\Parts\StorelocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Attachments\StorelocationAttachment;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\StorelocationParameter;
use App\Entity\UserSystem\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity represents a storage location, where parts can be stored.
 * @extends AbstractPartsContainingDBElement<StorelocationAttachment, StorelocationParameter>
 */
#[ORM\Entity(repositoryClass: StorelocationRepository::class)]
#[ORM\Table('`storelocations`')]
#[ORM\Index(name: 'location_idx_name', columns: ['name'])]
#[ORM\Index(name: 'location_idx_parent_name', columns: ['parent_id', 'name'])]
class Storelocation extends AbstractPartsContainingDBElement
{
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    protected ?AbstractStructuralDBElement $parent = null;

    /**
     * @var MeasurementUnit|null The measurement unit, which parts can be stored in here
     */
    #[ORM\ManyToOne(targetEntity: MeasurementUnit::class)]
    #[ORM\JoinColumn(name: 'storage_type_id')]
    protected ?MeasurementUnit $storage_type = null;

    /** @var Collection<int, StorelocationParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: StorelocationParameter::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => 'ASC', 'name' => 'ASC'])]
    protected Collection $parameters;

    /**
     * @var bool
     */
    #[Groups(['full', 'import'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $is_full = false;

    /**
     * @var bool
     */
    #[Groups(['full', 'import'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $only_single_part = false;

    /**
     * @var bool
     */
    #[Groups(['full', 'import'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $limit_to_existing_parts = false;

    /**
     * @var User|null The owner of this storage location
     */
    #[Assert\Expression('this.getOwner() == null or this.getOwner().isAnonymousUser() === false', message: 'validator.part_lot.owner_must_not_be_anonymous')]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_owner', onDelete: 'SET NULL')]
    protected ?User $owner = null;

    /**
     * @var bool If this is set to true, only parts lots, which are owned by the same user as the store location are allowed to be stored here.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    protected bool $part_owner_must_match = false;

    /**
     * @var Collection<int, StorelocationAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: StorelocationAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: StorelocationAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    protected ?Attachment $master_picture_attachment = null;

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Get the "is full" attribute.
     *
     * When this attribute is set, it is not possible to add additional parts or increase the instock of existing parts.
     *
     * @return bool * true if the store location is full
     *              * false if the store location isn't full
     */
    public function isFull(): bool
    {
        return $this->is_full;
    }

    /**
     * When this property is set, only one part (but many instock) is allowed to be stored in this store location.
     */
    public function isOnlySinglePart(): bool
    {
        return $this->only_single_part;
    }

    public function setOnlySinglePart(bool $only_single_part): self
    {
        $this->only_single_part = $only_single_part;

        return $this;
    }

    /**
     * When this property is set, it is only possible to increase the instock of parts, that are already stored here.
     */
    public function isLimitToExistingParts(): bool
    {
        return $this->limit_to_existing_parts;
    }

    public function setLimitToExistingParts(bool $limit_to_existing_parts): self
    {
        $this->limit_to_existing_parts = $limit_to_existing_parts;

        return $this;
    }

    public function getStorageType(): ?MeasurementUnit
    {
        return $this->storage_type;
    }

    public function setStorageType(?MeasurementUnit $storage_type): self
    {
        $this->storage_type = $storage_type;

        return $this;
    }

    /**
     * Returns the owner of this storage location
     */
    public function getOwner(): ?User
    {
        return $this->owner;
    }

    /**
     * Sets the owner of this storage location
     */
    public function setOwner(?User $owner): Storelocation
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * If this is set to true, only parts lots, which are owned by the same user as the store location are allowed to be stored here.
     */
    public function isPartOwnerMustMatch(): bool
    {
        return $this->part_owner_must_match;
    }

    /**
     * If this is set to true, only parts lots, which are owned by the same user as the store location are allowed to be stored here.
     */
    public function setPartOwnerMustMatch(bool $part_owner_must_match): Storelocation
    {
        $this->part_owner_must_match = $part_owner_must_match;
        return $this;
    }




    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/
    /**
     * Change the "is full" attribute of this store location.
     *
     *     "is_full" = true means that there is no more space in this storelocation.
     *     This attribute is only for information, it has no effect.
     *
     * @param bool $new_is_full * true means that the storelocation is full
     *                          * false means that the storelocation isn't full
     */
    public function setIsFull(bool $new_is_full): self
    {
        $this->is_full = $new_is_full;

        return $this;
    }
    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->parameters = new ArrayCollection();
        $this->attachments = new ArrayCollection();
    }
}
