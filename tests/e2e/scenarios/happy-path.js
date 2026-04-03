'use strict';

/**
 * Happy-path scenarios — customers with clear needs, cooperative dialogue,
 * and a natural path to a GrayFox plan.
 *
 * KB context (from current install):
 *   - Product: WordPress plugin — AI chat widget, website builder, booking,
 *              Google Drive sync, Google Sheets analytics
 *   - Plans: Trial (5 docs), Starter (20 docs), Growth (booking + Drive),
 *            Pro (everything unlimited + Sheets analytics)
 */

module.exports = [

	{
		id:      'HP-01',
		name:    'Hair salon owner — wants AI chatbot + online booking',
		persona: 'Maya, 38. Runs a 3-chair hair salon. Gets flooded with calls asking hours/prices. Uses Google Calendar already. Low tech knowledge.',
		type:    'happy-path',
		messages: [
			'hi',
			'I run a small hair salon and I spend half my day answering the same questions on the phone — hours, prices, how to book',
			'yeah exactly, pricing and booking are the big ones',
			'yes I already use Google Calendar for appointments',
			'I have a WordPress website but I barely know how it works',
			'how much would the plan with booking cost?',
			'that sounds reasonable, my email is mayacuts@gmail.com',
		],
		expect: {
			mentionsBooking:     true,
			mentionsGoogleCal:   true,
			asksForEmail:        true,
			noErrors:            true,
		},
	},

	{
		id:      'HP-02',
		name:    'Freelance nutritionist — wants to automate FAQ and capture leads',
		persona: 'Sofia, 29. Online nutritionist, lots of DMs asking the same questions. Has a basic WordPress site. Medium tech knowledge.',
		type:    'happy-path',
		messages: [
			'hello',
			'Sofia',
			'I\'m a nutritionist and I get tons of questions on Instagram and my website — what diet plans do I offer, what\'s the cost, how to get started',
			'I already have a WordPress website',
			'I don\'t really take appointments, it\'s more about people signing up for my plans',
			'I have a PDF of my programs and pricing',
			'can the chatbot learn from that PDF?',
			'yes, that\'s exactly what I need. Can I try it first before paying?',
		],
		expect: {
			mentionsDocumentUpload: true,
			mentionsTrial:          true,
			noErrors:               true,
		},
	},

	{
		id:      'HP-03',
		name:    'Personal trainer — booking automation focus',
		persona: 'Derek, 34. Personal trainer running a small gym. Books sessions manually via text. No website. Low tech knowledge.',
		type:    'happy-path',
		messages: [
			'hey there',
			'Derek',
			'I\'m a personal trainer, I have a small gym and I\'m tired of going back and forth with clients over text to schedule sessions',
			'I don\'t really have a website, just a Facebook page',
			'my clients mostly find me on Instagram and then text me to book',
			'how would booking work without a website?',
			'do I need to set all of this up myself or is there help?',
			'what plan would I need for the booking feature?',
		],
		expect: {
			mentionsBooking:  true,
			mentionsWordPress: true,
			noErrors:         true,
		},
	},

	{
		id:      'HP-04',
		name:    'Restaurant owner — wants customer FAQ automation',
		persona: 'Marco, 52. Runs a family Italian restaurant. Gets constant calls about hours, menu, reservations. Has someone who manages the website. Medium tech knowledge.',
		type:    'happy-path',
		messages: [
			'good morning',
			'Marco',
			'I own a restaurant and my staff is constantly answering the phone for the same questions — are you open on Monday, do you have vegan options, can I make a reservation',
			'yes we have a website, my nephew manages it, it\'s WordPress I think',
			'we have a PDF menu and a document with our hours and policies',
			'can it answer questions in Spanish too? We have a lot of Spanish-speaking customers',
			'and can people reserve a table through the chat?',
			'what would something like this cost?',
		],
		expect: {
			mentionsDocumentUpload: true,
			noErrors:               true,
		},
	},

	{
		id:      'HP-05',
		name:    'Tech-savvy startup founder — evaluating Gray Fox for customer support',
		persona: 'Priya, 31. CTO of a small SaaS startup. Wants to add AI support to their WordPress marketing site. High technical knowledge. Evaluating options carefully.',
		type:    'happy-path',
		messages: [
			'hi',
			'Priya',
			'I\'m evaluating AI chat solutions for our WordPress marketing site. We need something that can answer questions about our product without hallucinating',
			'we\'re using Anthropic Claude currently for other things, can we use our own API key?',
			'and where is the data stored? We have GDPR obligations',
			'we\'d need to upload probably 30-40 documents — feature specs, pricing tiers, integration guides',
			'does it support webhook callbacks when a lead is captured?',
			'and what about analytics on the conversations?',
			'this sounds promising, can you walk me through the Pro plan?',
		],
		expect: {
			mentionsApiKey:     true,
			mentionsDataOwner:  true,
			mentionsPro:        true,
			noErrors:           true,
		},
	},

	{
		id:      'HP-06',
		name:    'Real estate agent — property FAQ chatbot',
		persona: 'James, 45. Independent real estate agent. Gets lots of website inquiries asking about listings, availability, and process. Has WordPress. Medium tech.',
		type:    'happy-path',
		messages: [
			'hi there',
			'James here',
			'I\'m a realtor and I have a WordPress website with property listings and a lot of people filling out contact forms asking basic questions I could answer automatically',
			'things like how does the buying process work, what neighborhoods do you cover, what are your fees',
			'I have a Word document with my full buyer and seller guide, about 12 pages',
			'can I upload that and have the chatbot answer from it?',
			'and I add new listings every week, can it stay updated automatically?',
		],
		expect: {
			mentionsDocumentUpload: true,
			mentionsDriveSync:      true,
			noErrors:               true,
		},
	},

];
