<?php

namespace Shel\Neos\TransferContent\Controller;

use Neos\ContentRepository\Domain\Repository\ContentDimensionRepository;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Error\Messages\Message;
use Neos\Flow\I18n\Translator;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Service\NodeOperations;
use Neos\ContentRepository\Domain\Service\ContextFactory;
use Neos\ContentRepository\Exception\NodeException;

/**
 * Controller
 *
 * @Flow\Scope("singleton")
 */
class ContentTransferController extends AbstractModuleController
{

    /**
     * @Flow\InjectConfiguration(package="Shel.Neos.TransferContent")
     * @var array
     */
    protected $dimensionSettings = array();

    /**
     * @var NodeOperations
     * @Flow\Inject
     */
    protected $nodeOperations;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject()
     * @var ContentDimensionRepository
     */
    protected $contentDimensionRepository;

    /**
     * @Flow\Inject
     * @var ContextFactory
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    /**
     * Shows form to transfer content
     * @param Site $sourceSite
     * @param Site $targetSite
     * @param string $targetParentNodePath
     * @param array $modes
     * @param string $mode
     */
    public function indexAction(Site $sourceSite = null, Site $targetSite = null, $targetParentNodePath = '', $modes = [], $mode = 'copy')
    {
        $sites = $this->siteRepository->findOnline();

        $this->view->assignMultiple([
            'sites' => $sites,
            'sourceSite' => $sourceSite,
            'targetSite' => $targetSite,
            'targetParentNodePath' => $targetParentNodePath,
            'modes' => $this->getModes(),
            'mode' => $mode
        ]);
    }

    /**
     * prepare modes for select box
     *
     * @return array
     */
    public function getModes() : array
    {
        $entries = array('copy', 'move');
        foreach ($entries as $entry) {
            $mode = new \stdClass();
            $mode->key = $entry;
            $mode->value = $this->translate('mode.' . $entry);
            $modes[] = $mode;
        }
        return $modes;
    }

    /**
     * @param Site $sourceSite
     * @param Site $targetSite
     * @param string $sourceNodePath
     * @param string $targetParentNodePath
     * @param string $mode
     *
     * @throws \Neos\Eel\Exception
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     * @Flow\Validate(argumentName="sourceNodePath", type="\Neos\Flow\Validation\Validator\NotEmptyValidator")
     * @Flow\Validate(argumentName="targetParentNodePath", type="\Neos\Flow\Validation\Validator\NotEmptyValidator")
     */
    public function copyNodeAction(Site $sourceSite, Site $targetSite, $sourceNodePath, $targetParentNodePath, $mode = 'copy')
    {
        /** @var ContentContext $sourceContext */
        $sourceContext = $this->contextFactory->create([
            'currentSite' => $sourceSite,
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true
        ]);

        /** @var ContentContext $targetContext */
        $targetContext = $this->contextFactory->create([
            'currentSite' => $targetSite,
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true
        ]);

        $sourceNode = $sourceContext->getNodeByIdentifier($sourceNodePath);
        $targetParentNode = $targetContext->getNodeByIdentifier($targetParentNodePath);

        if ($sourceNode === null) {
            $this->addFlashMessage(
                $this->translate('error.sourceNodeNotFound'),
                'Error',
                Message::SEVERITY_ERROR
            );
        } else if ($targetParentNode === null) {
            $this->addFlashMessage(
                $this->translate('error.targetParentNodeNotFound'),
                'Error',
                Message::SEVERITY_ERROR
            );
        } else if (!$sourceNode->getNodeType()->isOfType('Neos.Neos:Document')) {
            $this->addFlashMessage(
                $this->translate('error.invalidSourceNode', [$sourceNode->getNodeType()]),
                'Error',
                Message::SEVERITY_ERROR
            );
        } else if (!$targetParentNode->getNodeType()->isOfType('Neos.Neos:Document')) {
            $this->addFlashMessage(
                $this->translate('error.invalidTargetParentNode', [$targetParentNode->getNodeType()]),
                'Error',
                Message::SEVERITY_ERROR
            );
        } else if (!$targetParentNode->isNodeTypeAllowedAsChildNode($sourceNode->getNodeType())) {
            $this->addFlashMessage(
                $this->translate('error.sourceNodeNotAllowedAsChildNode'),
                'Error',
                Message::SEVERITY_ERROR
            );
        } else {
            try {

                if ($mode === 'move')
                {
                    // Default language
                    $this->nodeOperations->move($sourceNode, $targetParentNode, 'into');

                    // Doesn't return an array with all dimensions combinations ... no clue why :(
                    // $availableDimensions = $this->contentDimensionRepository->findAll();

                    $dimensions = $this->dimensionSettings;

                    if (!$dimensions) {
                        $this->addFlashMessage(
                            $this->translate('message.moved'),
                            'Success'
                        );
                        $this->redirect('index', null, null, [
                            'sourceSite' => $sourceSite,
                            'targetSite' => $targetSite,
                            'targetParentNodePath' => $targetParentNodePath,
                            'modes' => $this->getModes(),
                            'mode' => $mode
                        ]);
                    }

                    // start move nodes for other dimension not default
                    foreach ($dimensions as $dimension)
                    {
                        $sourceDimensionContext = $this->getDimensionContext(
                            $sourceSite,
                            $dimension['dimensions'],
                            $dimension['targetDimensions']
                        );

                        $targetDimensionContext = $this->getDimensionContext(
                            $targetSite,
                            $dimension['dimensions'],
                            $dimension['targetDimensions']
                        );

                        $sourceNodeDimension = $sourceDimensionContext->getNodeByIdentifier($sourceNodePath);
                        $targetParentNodeDimension = $targetDimensionContext->getNodeByIdentifier($targetParentNodePath);

                        if ($sourceNodeDimension && $targetParentNodeDimension) {
                            $this->nodeOperations->move($sourceNodeDimension, $targetParentNodeDimension, 'into');
                        } else {
                            $this->addFlashMessage(
                                $this->translate('warning.sourceNodeDimensionNull', [implode('_',$dimension['targetDimensions'])]),
                                'Warning',
                                Message::SEVERITY_WARNING
                            );
                        }
                    }

                    $this->addFlashMessage(
                        $this->translate('message.moved'),
                        'Success'
                    );

                } else {
                    $this->nodeOperations->copy($sourceNode, $targetParentNode, 'into');
                    $this->addFlashMessage(
                        $this->translate('message.copied'),
                        'Success'
                    );
                }

            } catch (NodeException $e) {
                $this->addFlashMessage(
                    $this->translate('error.copyFailed', [$e->getReferenceCode()]),
                    'Error',
                    Message::SEVERITY_ERROR
                );
            }
        }

        $this->redirect('index', null, null, [
            'sourceSite' => $sourceSite,
            'targetSite' => $targetSite,
            'targetParentNodePath' => $targetParentNodePath,
            'mode' => $mode
        ]);
    }

    /**
     * @param string $id
     * @param array $arguments
     * @return string
     */
    protected function translate($id, array $arguments = [])
    {
        return $this->translator->translateById($id, $arguments, null, null, 'ContentTransfer', 'Shel.Neos.TransferContent');
    }

    /**
     * @param Site $sourceSite
     *
     * @param array $dimensions e.g. ['country' => ['de'],'language' => ['en']]
     * @param array $targetDimensions e.g. ['country' => 'de','language' => 'en']
     *
     * @return Context
     */
    public function getDimensionContext(Site $sourceSite, array $dimensions, array $targetDimensions): Context
    {
        return $this->contextFactory->create([
            'currentSite' => $sourceSite,
            'dimensions' => $dimensions,
            'targetDimensions' => $targetDimensions,
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true
        ]);
    }
}
