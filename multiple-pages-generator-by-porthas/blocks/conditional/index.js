/**
 * WordPress dependencies.
 */

import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { Panel, PanelBody, SelectControl} from '@wordpress/components';
import { assign } from 'lodash';
import classnames from 'classnames'; 
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import FilterRepeater from '../../components/FilterRepeater';
 
import './editor.scss';
const operators = window.mpgCondData.operators || []    ;
import { addFilter } from '@wordpress/hooks';
import {
	Fragment
} from '@wordpress/element';

const addAttribute = ( props ) => {
    props.attributes = assign( props.attributes, {
        mpgConditions: {
            type: 'object',
            default: {
                conditions: [],
                logic: 'all'
            },
            properties: {
                conditions: {
                    type: 'array',
                    items: {
                        type: 'object',
                        properties: {
                            column: { type: 'string' },
                            operator: { type: 'string' },
                            value: { type: 'string' }
                        }
                    }
                },
                logic: {
                    type: 'string',
                    enum: ['all', 'any']
                }
            }
        }
    });
    return props;
};

const withConditions = createHigherOrderComponent( BlockEdit => {
    return props => {
        const { attributes, setAttributes } = props;
        const [mpgConditions, setMpgConditions] = useState(attributes.mpgConditions || { conditions: [], logic: 'all' });
 
        const updateConditions = (newConditions) => {
            setMpgConditions(newConditions);
            setAttributes({ mpgConditions: newConditions });
        };
        const transformedOperators = Object.entries(operators).map(([value, label]) => ({
            label: __(label, 'multi-pages-plugin'),
            value
        }));
        const CompareOperators = Object.keys(window.mpgCondData.compareOperators) || [];

        return (
            <Fragment>
                <BlockEdit { ...props } />

                <InspectorControls>
                    <PanelBody title={__('MPG Visibility Conditions', 'multi-pages-plugin')}>
                        
                        
                        <FilterRepeater
                            onChange={updateConditions}
                            conditions={mpgConditions}
							updateConditions={updateConditions}
							operators={transformedOperators}
							CompareOperators={CompareOperators}
                            labels={{ 
                                addFilter: __('Add Condition', 'multi-pages-plugin'),
                                applyFilters: __('Show If', 'multi-pages-plugin'),
                                matchAll: __('All conditions are met', 'multi-pages-plugin'),
                                matchAny: __('Any condition is met', 'multi-pages-plugin'),
                                removeFilter: __('Remove Condition', 'multi-pages-plugin'),
                                filterTitle: __('Condition', 'multi-pages-plugin'),
                                column: __('Column name', 'multi-pages-plugin'),
                                operator: __('Condition', 'multi-pages-plugin'),
                                value: __('Value', 'multi-pages-plugin'),
                            }}
                        />
                    </PanelBody>
                </InspectorControls>
            </Fragment>
        );
    };
}, 'withConditions' );

const withConditionsIndicator = createHigherOrderComponent( BlockListBlock => {
	return props => {
        const { attributes } = props;
        const hasConditions = attributes.mpgConditions && 
                              attributes.mpgConditions.conditions && 
                              attributes.mpgConditions.conditions.length > 0;

        return (
        <BlockListBlock
            { ...props }
            className={ classnames(props.className, {
                'mpg-has-condition': hasConditions
            }) }
            wrapperProps={{
                ...props.wrapperProps,
                'data-mpg-label': hasConditions ? __('MPG Conditioned', 'multi-pages-plugin') : undefined
            }}
        />
    );
	};
}, 'withConditionsIndicator' );

addFilter( 'editor.BlockEdit', 'mpg/conditions-inspector', withConditions, 3 );
addFilter( 'blocks.registerBlockType', 'mpg/conditions-register', addAttribute );
addFilter( 'editor.BlockListBlock', 'mpg/contextual-indicators', withConditionsIndicator );
