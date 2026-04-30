const Joi = require('joi');

const updateProfileSchema = Joi.object({
    school_name: Joi.string().trim().max(255),
    school_type: Joi.string().valid('nursery', 'primary', 'secondary', 'tertiary', 'vocational'),
    address: Joi.string().trim(),
    city: Joi.string().trim().max(100),
    state: Joi.string().trim().max(100),
    contact_person_name: Joi.string().trim().max(255),
    contact_phone: Joi.string().trim().max(20),
    website: Joi.string().uri().allow('', null),
    description: Joi.string().trim().allow('', null)
}).min(1);

module.exports = {
    updateProfileSchema
};
