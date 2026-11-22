<?php

declare(strict_types=1);

namespace Myca\Content\Block\Widget;

use Magento\Newsletter\Block\Subscribe;
use Magento\Widget\Block\BlockInterface;

/**
 * Widget « MYCA – Inscription à la newsletter » (bloc « Rejoignez le cercle MYCA » de l'accueil).
 * Titre, texte et mention de consentement sont modifiables depuis Page Builder.
 */
class Newsletter extends Subscribe implements BlockInterface
{
    /** @var string */
    protected $_template = 'Myca_Content::widget/newsletter.phtml';
}
