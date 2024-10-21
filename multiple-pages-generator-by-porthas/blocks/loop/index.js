/**
 * WordPress dependencies
 */
import { __, _x } from '@wordpress/i18n';
import './editor.scss';
import { registerBlockType } from '@wordpress/blocks';

import { InnerBlocks, useBlockProps,InspectorControls, ButtonBlockAppender } from '@wordpress/block-editor';

import {  useEffect, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';
import { PanelBody, SelectControl, Button, TextControl, ToggleControl } from '@wordpress/components';
/**
 * Internal dependencies 
 */
import metadata from './block.json';
import FilterRepeater from '../../components/FilterRepeater';


const { name } = metadata; 

registerBlockType( name, {
	...metadata,
	edit: ({attributes, setAttributes, clientId}) => {
		const { project_id, direction, conditions } = attributes;
        const blockProps = useBlockProps();
		useEffect(() => {
        }, [attributes]);
        const [showPreview, setShowPreview] = useState(false);
        const [innerBlocksContent, setInnerBlocksContent] = useState('');

        const { selectBlock } = useDispatch('core/block-editor');

        // Access the localized data and format it for SelectControl
        const projectOptions = window.mpgLoop ? Object.entries(window.mpgLoop.projects).map(([id, name]) => ({
            value: id,
            label: name
        })) : [];
		const ordersData = window.mpgLoop ? Object.entries(window.mpgLoop.orders).map(([id, name]) => ({
            value: id,
            label: name
        })) : [];
        const isSelected = useSelect((select) => {
            const { isBlockSelected, hasSelectedInnerBlock } = select('core/block-editor');
            return isBlockSelected(clientId) || hasSelectedInnerBlock(clientId, true);
        }, [clientId]);

        useEffect(() => {
            if (isSelected) {
                setShowPreview(false);
            }
        }, [isSelected]);
 
        const handlePreviewClick = () => {
            setShowPreview(true);
            selectBlock(null); // This will deselect the current block
        };

        const { getBlocks } = useSelect((select) => select('core/block-editor'));

        useEffect(() => {
            const updateInnerBlocksContent = () => {
                const blocks = getBlocks(clientId);
                const content = wp.blocks.serialize(blocks);
                setInnerBlocksContent(content);
            };

            updateInnerBlocksContent();
            
            // Set up a listener for block changes
            const unsubscribe = wp.data.subscribe(() => {
                updateInnerBlocksContent();
            });

            return () => unsubscribe();
        }, [clientId, getBlocks]);

        // Get the header data for the selected project
        const projectHeaders = window.mpgLoop && project_id ? window.mpgLoop.projectHeaders[project_id] : [];

		const operators = window.mpgLoop.operators || []    ;
        // Format the header data for SelectControl
        const headerOptions = projectHeaders.map(header => ({
            value: header,
            label: header
        })); 
        const transformedOperators = Object.entries(operators).map(([value, label]) => ({
            label,
            value
        })); 

        const CompareOperators = Object.keys(window.mpgLoop.compareOperators) || [];
		

        const updateConditions = (newConditions) => {
            setAttributes({ conditions: newConditions });
        };


        const hasInnerBlocks = useSelect(
            (select) => {
                const { getBlockOrder } = select('core/block-editor');
                return getBlockOrder(clientId).length > 0;
            },
            [clientId]
        );

        return (
			<>
				<InspectorControls>
					<PanelBody className="mpg-loop-settings" title={__('Loop Settings', 'multi-pages-plugin')}>
						<SelectControl
							label={__('Select Project', 'multi-pages-plugin')}
							value={attributes.project_id}
							options={[
								{ value: 0, label: __('Select a project...', 'multi-pages-plugin') },
								...projectOptions
							]}
							onChange={(newProjectId) => {
								setAttributes({ project_id: parseInt(newProjectId) });
								setShowPreview(false); // Reset preview when project changes
							}}
						/>
						{project_id > 0 && (
							<>
								<TextControl
									label={__('Limit', 'multi-pages-plugin')}
									value={attributes.limit}
                                    type={'number'}
									onChange={(limit) => setAttributes({ limit: parseInt(limit) })}
									help={__('Number of maximum items to display.', 'multi-pages-plugin')}
								/>
								<ToggleControl
									label={__('Unique Rows', 'multi-pages-plugin')}
									checked={attributes.uniqueRows}
									onChange={(uniqueRows) => setAttributes({ uniqueRows })}
									help={__('Display only unique rows', 'multi-pages-plugin')}
								/>
								<SelectControl
									label={__('Ordering', 'multi-pages-plugin')}
									value={attributes.direction}
									options={[
										{ value: '', label: __('...', 'multi-pages-plugin') },
										...ordersData
									]}
									onChange={(direction) => setAttributes({ direction })}
								/>
								{(direction === 'asc' || direction === 'desc') && (
									<SelectControl
										label={__('Order By', 'multi-pages-plugin')}
										value={attributes.orderBy}
										options={[
											{ value: '', label: __('Select a column...', 'multi-pages-plugin') },
											...headerOptions
										]}
										onChange={(orderBy) => setAttributes({ orderBy })}
										help={__('Column name to order by', 'multi-pages-plugin')}
									/>
								)}
								<Button
									isPrimary
									onClick={handlePreviewClick}
								>
									{__('Preview Loop', 'multi-pages-plugin')}
								</Button>
							</>
						)}
					</PanelBody>
					<PanelBody 
						title={__('Loop Filters', 'multi-pages-plugin')} 
						initialOpen={true}
						className={`mpg-loop-filters ${project_id > 0 ? '' : 'hidden'}`}
						insertAfter="mpg-loop-settings"
					>
						<FilterRepeater
							conditions={conditions}
							updateConditions={updateConditions}
							columnOptions={headerOptions}
							operators={transformedOperators}
							CompareOperators={CompareOperators}
						/>
					</PanelBody>
				</InspectorControls>
				<div {...blockProps}>
                    {showPreview && !isSelected ? (
                        <ServerSideRender
                            block="mpg/loop"
                            attributes={{
                                ...attributes,
                                innerBlocksContent: innerBlocksContent
                            }}
                            httpMethod="POST"
                        />
                    ) : (
                        <InnerBlocks
                            renderAppender={() =>
                                !hasInnerBlocks ? (
                                    <ButtonBlockAppender
                                        rootClientId={clientId}
                                        className="wp-block-mpg-loop__appender"
                                    />
                                ) : (
                                    <InnerBlocks.DefaultBlockAppender />
                                )
                            }
                        />
                    )}
                </div>
			</>
		);

	},

	save: () => {
		const blockProps = useBlockProps.save();

		return (
			<div { ...blockProps }>
				<InnerBlocks.Content />
			</div>
		);
	},
});
