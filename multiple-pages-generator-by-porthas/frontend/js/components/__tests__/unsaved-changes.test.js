import {
	clearUnsavedChanges,
	hasUnsavedChanges,
	markUnsavedChanges,
	persistProjectChanges,
} from '../unsaved-changes.js';

describe( 'project unsaved-change state', () => {
	beforeEach( () => {
		clearUnsavedChanges();
	} );

	it( 'can be marked and cleared explicitly', () => {
		expect( hasUnsavedChanges() ).toBe( false );
		markUnsavedChanges();
		expect( hasUnsavedChanges() ).toBe( true );
		clearUnsavedChanges();
		expect( hasUnsavedChanges() ).toBe( false );
	} );

	it( 'does not attempt the URL save when the main save fails', async () => {
		markUnsavedChanges();
		const saveUrl = jest.fn();

		const result = await persistProjectChanges(
			jest.fn().mockResolvedValue( false ),
			saveUrl
		);

		expect( result.success ).toBe( false );
		expect( saveUrl ).not.toHaveBeenCalled();
		expect( hasUnsavedChanges() ).toBe( true );
	} );

	it( 'keeps dirty state when URL validation or persistence fails', async () => {
		markUnsavedChanges();

		const result = await persistProjectChanges(
			jest.fn().mockResolvedValue( 42 ),
			jest.fn().mockResolvedValue( false )
		);

		expect( result ).toEqual( { success: false, projectId: 42 } );
		expect( hasUnsavedChanges() ).toBe( true );
	} );

	it( 'keeps dirty state when either request rejects', async () => {
		markUnsavedChanges();
		const error = new Error( 'network unavailable' );

		const result = await persistProjectChanges(
			jest.fn().mockRejectedValue( error ),
			jest.fn()
		);

		expect( result.success ).toBe( false );
		expect( result.error ).toBe( error );
		expect( hasUnsavedChanges() ).toBe( true );
	} );

	it( 'clears dirty state only after both saves succeed', async () => {
		markUnsavedChanges();
		const saveMain = jest.fn().mockResolvedValue( 42 );
		const saveUrl = jest.fn().mockResolvedValue( true );

		const result = await persistProjectChanges( saveMain, saveUrl );

		expect( saveMain ).toHaveBeenCalledTimes( 1 );
		expect( saveUrl ).toHaveBeenCalledWith( 42 );
		expect( result ).toEqual( { success: true, projectId: 42 } );
		expect( hasUnsavedChanges() ).toBe( false );
	} );
} );
